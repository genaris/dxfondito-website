import { useEffect, useRef, useState } from 'react'
import type { PointerEvent } from 'react'
import { ApiError } from '../api.ts'
import { detectBoxes, fieldsFromBoxes, otherFields, pixelColour, READING_ORDER, swapBoxes } from '../detect.ts'
import type { Pixels, Rgb } from '../detect.ts'
import { changeError } from '../messages.ts'
import {
  anchorX,
  baseline,
  FIELD_LABELS,
  fitBox,
  FONT_LABELS,
  FONTS,
  fontUrl,
  fullSize,
  HANDLES,
  MAX_HEIGHT,
  MAX_IMAGE_BYTES,
  MAX_SIDE,
  MIN_HEIGHT,
  MIN_WIDTH,
  moveBox,
  resizeBox,
  sharedSizes,
  textSize,
} from '../qsl.ts'
import type { Align, Box, Field, Fields, Font, Handle, TemplateKind } from '../qsl.ts'

const ANCHORS: Record<Align, 'start' | 'middle' | 'end'> = { left: 'start', center: 'middle', right: 'end' }

/**
 * The @font-face rules of the fonts of the API. The editor draws the text with the same fonts as the API.
 */
function TemplateFonts() {
  const css = FONTS.map(
    (font) => `@font-face { font-family: 'qsl-${font}'; src: url('${fontUrl(font)}') format('truetype'); }`,
  ).join('\n')
  return <style>{css}</style>
}

/** A move of a box, or a change of its size with a handle. */
type Drag = { name: string; handle: Handle | null; start: { x: number; y: number }; box: Box }

const measureCanvas = typeof document === 'undefined' ? null : document.createElement('canvas').getContext('2d')

/** The width of a text in pixels, with the font of the API. */
function measure(text: string, font: Font, size: number): number {
  if (!measureCanvas) return 0
  measureCanvas.font = `${size}px qsl-${font}`
  return measureCanvas.measureText(text).width
}

/** The pixels of a loaded image, for the search of the field boxes. Null if the browser cannot read them. */
function readPixels(image: HTMLImageElement): Pixels | null {
  try {
    const canvas = document.createElement('canvas')
    canvas.width = image.naturalWidth
    canvas.height = image.naturalHeight
    const context = canvas.getContext('2d', { willReadFrequently: true })
    if (!context) return null
    context.drawImage(image, 0, 0)
    return context.getImageData(0, 0, canvas.width, canvas.height)
  } catch {
    return null
  }
}

const ORDER_TEXT = READING_ORDER.map((name) => FIELD_LABELS[name]).join(', ')

/** The message of a search of the field boxes. */
function detectionNotice(found: number, ok: boolean, chosenColour: boolean): string {
  if (ok) {
    return (
      `Se encontraron los ${found} recuadros y se asignaron en el orden habitual: ${ORDER_TEXT}. ` +
      'Revise que cada campo esté en su recuadro. Si dos están cambiados, use «Cambiar lugar con».'
    )
  }
  const what = found === 0 ? 'No se encontraron recuadros' : `Se encontraron ${found} recuadros`
  const colour = chosenColour ? 'de ese color' : 'de color en la parte inferior'
  return (
    `${what} ${colour}, y hacen falta ${READING_ORDER.length}. Ubique los campos con el mouse, ` +
    'o elija el color de los recuadros con un clic sobre uno de ellos.'
  )
}

/** The position of a handle on a box. */
function handlePoint(box: Box, handle: Handle): { x: number; y: number } {
  const x = handle.includes('w') ? box.x : handle.includes('e') ? box.x + box.width : box.x + box.width / 2
  const y = handle.includes('n') ? box.y : handle.includes('s') ? box.y + box.height : box.y + box.height / 2
  return { x, y }
}

const CURSORS: Record<Handle, string> = {
  nw: 'nwse-resize',
  se: 'nwse-resize',
  ne: 'nesw-resize',
  sw: 'nesw-resize',
  n: 'ns-resize',
  s: 'ns-resize',
  e: 'ew-resize',
  w: 'ew-resize',
}

/** A saved template: the address of its image, its fields and the size of its image. */
export interface SavedTemplate {
  imageUrl: string
  fields: Fields
  width: number
  height: number
}

/**
 * The editor of a template of a QSL card or a certificate: the image, and the position, size, colour, alignment
 * and font of each field (FR-QSL-4, FR-CER-3). The user can see the sample of the API before the save operation
 * (FR-QSL-5).
 */
export function TemplateEditor({
  kind,
  title,
  template,
  save: saveFields,
  preview: previewFields,
  onDone,
  onCancel,
}: {
  kind: TemplateKind
  title: string
  template: SavedTemplate | null
  save: (fields: Fields, file: File | null) => Promise<unknown>
  /** The sample image of the API, as an object URL. */
  preview: (fields: Fields, file: File | null) => Promise<string>
  onDone: () => void
  onCancel: () => void
}) {
  const [file, setFile] = useState<File | null>(null)
  const [fileUrl, setFileUrl] = useState<string | null>(null)
  const [size, setSize] = useState(template ? { width: template.width, height: template.height } : null)
  const [fields, setFields] = useState<Fields | null>(template?.fields ?? null)
  const [selected, setSelected] = useState<string>(kind.names[0])
  const [preview, setPreview] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [scale, setScale] = useState(1)
  const [detectNotice, setDetectNotice] = useState<string | null>(null)
  const [picking, setPicking] = useState(false)
  const pixelsRef = useRef<Pixels | null>(null)
  const [, setFontsReady] = useState(0)
  const svgRef = useRef<SVGSVGElement>(null)
  const dragRef = useRef<Drag | null>(null)

  // The text measure needs the fonts. A new render after their load gives the correct sizes.
  useEffect(() => {
    let active = true
    Promise.all(FONTS.map((font) => document.fonts.load(`20px qsl-${font}`)))
      .then(() => {
        if (active) setFontsReady((value) => value + 1)
      })
      .catch(() => undefined)
    return () => {
      active = false
    }
  }, [])

  // The pixels of the image for one pixel of the screen. The labels and the handles keep their size on the screen.
  // The SVG layer exists only with the fields. Thus the observer starts again when they appear.
  const hasFields = fields !== null
  useEffect(() => {
    const svg = svgRef.current
    if (!svg || !size) return
    const observer = new ResizeObserver(() => {
      const shown = svg.getBoundingClientRect().width
      if (shown > 0) setScale(size.width / shown)
    })
    observer.observe(svg)
    return () => observer.disconnect()
  }, [size, hasFields])

  const imageUrl = fileUrl ?? template?.imageUrl ?? null

  // The object URLs use memory until the page revokes them.
  useEffect(() => () => {
    if (fileUrl) URL.revokeObjectURL(fileUrl)
  }, [fileUrl])
  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview)
  }, [preview])

  function selectFile(selectedFile: File | null) {
    setError(null)
    setPreview(null)
    if (!selectedFile) return
    if (!/\.(jpe?g|png)$/i.test(selectedFile.name)) {
      setError('La imagen debe ser JPEG o PNG.')
      return
    }
    if (selectedFile.size > MAX_IMAGE_BYTES) {
      setError('La imagen es demasiado grande. El máximo es 10 MB.')
      return
    }
    setFile(selectedFile)
    setFileUrl(URL.createObjectURL(selectedFile))
    setSize(null)
    setDetectNotice(null)
    setPicking(false)
  }

  function imageLoaded(image: HTMLImageElement) {
    const width = image.naturalWidth
    const height = image.naturalHeight
    if (width > MAX_SIDE || height > MAX_SIDE) {
      setError(`La imagen debe tener ${MAX_SIDE} píxeles o menos de cada lado.`)
      return
    }
    setSize({ width, height })
    pixelsRef.current = readPixels(image)
    // A new QSL card template gets the boxes of the image, or a first layout (FR-QSL-12).
    // A new image of a template keeps the boxes inside the image. The button searches the boxes again.
    if (!fields) {
      const first = kind.defaultFields(width, height)
      setFields((kind.detect ? detect(first, null, width, height) : null) ?? first)
      return
    }
    const kept: Fields = {}
    for (const name of kind.names) kept[name] = { ...fields[name], ...fitBox(fields[name], width, height) }
    setFields(kept)
  }

  /**
   * Searches the field boxes in the image. The result is the fields with the boxes of the image, or null.
   * The message tells the user the result.
   */
  function detect(base: Fields, colour: Rgb | null, width: number, height: number): Fields | null {
    const pixels = pixelsRef.current
    if (!pixels) {
      setDetectNotice('El navegador no pudo leer la imagen para buscar los recuadros. Ubique los campos con el mouse.')
      return null
    }
    const { boxes } = detectBoxes(pixels, colour)
    const found = fieldsFromBoxes(boxes, base, width, height)
    setDetectNotice(detectionNotice(boxes.length, found !== null, colour !== null))
    return found
  }

  /** The button: searches the boxes again, or with the colour of a click on the image. */
  function detectAgain(colour: Rgb | null) {
    if (!fields || !size) return
    const found = detect(fields, colour, size.width, size.height)
    if (found) {
      setPreview(null)
      setFields(found)
    }
  }

  function pickColour(event: PointerEvent<SVGSVGElement>) {
    const point = pointInImage(event)
    const pixels = pixelsRef.current
    if (!picking || !point || !pixels) return
    setPicking(false)
    detectAgain(pixelColour(pixels, point.x, point.y))
  }

  function change(name: string, changes: Partial<Field>) {
    setPreview(null)
    setFields((current) => (current ? { ...current, [name]: { ...current[name], ...changes } } : current))
  }

  function pointInImage(event: PointerEvent): { x: number; y: number } | null {
    const svg = svgRef.current
    const matrix = svg?.getScreenCTM()
    if (!svg || !matrix) return null
    const point = new DOMPoint(event.clientX, event.clientY).matrixTransform(matrix.inverse())
    return { x: point.x, y: point.y }
  }

  function startDrag(event: PointerEvent<SVGElement>, name: string, handle: Handle | null) {
    if (!fields) return
    const point = pointInImage(event)
    if (!point) return
    event.stopPropagation()
    event.currentTarget.setPointerCapture(event.pointerId)
    setSelected(name)
    const { x, y, width, height } = fields[name]
    dragRef.current = { name, handle, start: point, box: { x, y, width, height } }
  }

  function moveDrag(event: PointerEvent) {
    const drag = dragRef.current
    const point = pointInImage(event)
    if (!drag || !point || !size) return
    const dx = point.x - drag.start.x
    const dy = point.y - drag.start.y
    const box = drag.handle
      ? resizeBox(drag.box, drag.handle, dx, dy, size.width, size.height)
      : moveBox(drag.box, dx, dy, size.width, size.height)
    change(drag.name, box)
  }

  /** Changes one value of the box of the selected field from the panel. The box stays in the image. */
  function changeBox(changes: Partial<Box>) {
    if (!fields || !size) return
    change(selected, fitBox({ ...fields[selected], ...changes }, size.width, size.height))
  }

  async function showPreview() {
    if (!fields) return
    setBusy(true)
    setError(null)
    try {
      setPreview(await previewFields(fields, file))
    } catch (failure) {
      setError(editorError(failure, 'ver la muestra'))
    } finally {
      setBusy(false)
    }
  }

  async function save() {
    if (!fields) return
    setBusy(true)
    setError(null)
    try {
      await saveFields(fields, file)
      onDone()
    } catch (failure) {
      setError(editorError(failure, 'guardar la plantilla'))
      setBusy(false)
    }
  }

  const field = fields?.[selected]
  // The size of the sample text of each field, as the API writes it.
  const fits: Record<string, number> = {}
  if (fields) {
    for (const name of kind.names) {
      const item = fields[name]
      fits[name] = textSize(item, measure(kind.sample[name], item.font, fullSize(item)))
    }
  }
  const sizes = kind.sharedSize ? sharedSizes(fits) : fits

  return (
    <div className="qsl-editor">
      <TemplateFonts />
      <div className="title-row">
        <h4>{title}</h4>
        <label className="inline-field">
          {template ? 'Cambiar la imagen' : 'Imagen JPEG o PNG (hasta 10 MB)'}{' '}
          <input type="file" accept=".jpg,.jpeg,.png" onChange={(event) => selectFile(event.target.files?.[0] ?? null)} />
        </label>
      </div>

      {imageUrl && (
        <div className="editor-layout">
          <div className="canvas">
            <img
              src={imageUrl}
              alt="Plantilla"
              onLoad={(event) => imageLoaded(event.currentTarget)}
            />
            {size && fields && (
              <svg
                ref={svgRef}
                viewBox={`0 0 ${size.width} ${size.height}`}
                className={picking ? 'picking' : undefined}
                onPointerDown={pickColour}
                onPointerMove={moveDrag}
                onPointerUp={() => (dragRef.current = null)}
                onPointerCancel={() => (dragRef.current = null)}
              >
                {kind.names.map((name) => {
                  const item = fields[name]
                  const isSelected = name === selected
                  const fontSize = sizes[name]
                  return (
                    <g key={name} className={isSelected ? 'field selected' : 'field'}>
                      <rect
                        className="box"
                        x={item.x}
                        y={item.y}
                        width={item.width}
                        height={item.height}
                        strokeWidth={2}
                        onPointerDown={(event) => startDrag(event, name, null)}
                      />
                      <text
                        className="sample"
                        x={anchorX(item, item.align)}
                        y={baseline(item, fontSize)}
                        fontSize={fontSize}
                        fill={item.colour}
                        textAnchor={ANCHORS[item.align]}
                        fontFamily={`qsl-${item.font}, sans-serif`}
                      >
                        {kind.sample[name]}
                      </text>
                      <text className="label" x={item.x} y={item.y - 4 * scale} fontSize={12 * scale}>
                        {kind.labels[name]}
                      </text>
                      {isSelected &&
                        HANDLES.map((handle) => {
                          const point = handlePoint(item, handle)
                          const side = 10 * scale
                          return (
                            <rect
                              key={handle}
                              className="handle"
                              x={point.x - side / 2}
                              y={point.y - side / 2}
                              width={side}
                              height={side}
                              strokeWidth={1.5}
                              style={{ cursor: CURSORS[handle] }}
                              onPointerDown={(event) => startDrag(event, name, handle)}
                            />
                          )
                        })}
                    </g>
                  )
                })}
              </svg>
            )}
          </div>

          {fields && field && size && (
            <div className="field-panel">
              <div className="field-list" role="tablist" aria-label="Campos">
                {kind.names.map((name) => (
                  <button
                    key={name}
                    type="button"
                    role="tab"
                    aria-selected={name === selected}
                    className={name === selected ? 'chip active' : 'chip'}
                    onClick={() => setSelected(name)}
                  >
                    {kind.labels[name]}
                  </button>
                ))}
              </div>
              {kind.detect && (
                <div className="detect-actions">
                  <button type="button" onClick={() => detectAgain(null)} disabled={busy}>
                    Detectar recuadros
                  </button>
                  <button type="button" onClick={() => setPicking((value) => !value)} disabled={busy} aria-pressed={picking}>
                    {picking ? 'Cancelar la elección' : 'Elegir el color'}
                  </button>
                </div>
              )}
              {picking && <p className="hint">Haga clic sobre uno de los recuadros de la imagen.</p>}
              {detectNotice && (
                <p className="detect-notice" role="status">
                  {detectNotice}
                </p>
              )}
              <p className="hint">
                Arrastre cada recuadro para moverlo y sus bordes para cambiar el tamaño. El texto ocupa el alto del
                recuadro y se achica si no entra en el ancho.
              </p>
              <div className="form">
                <div className="field-row">
                  <label>
                    X
                    <input type="number" min={0} value={field.x} onChange={(event) => changeBox({ x: Number(event.target.value) })} />
                  </label>
                  <label>
                    Y
                    <input type="number" min={0} value={field.y} onChange={(event) => changeBox({ y: Number(event.target.value) })} />
                  </label>
                </div>
                <div className="field-row">
                  <label>
                    Ancho
                    <input
                      type="number"
                      min={MIN_WIDTH}
                      value={field.width}
                      onChange={(event) => changeBox({ width: Number(event.target.value) })}
                    />
                  </label>
                  <label>
                    Alto
                    <input
                      type="number"
                      min={MIN_HEIGHT}
                      max={MAX_HEIGHT}
                      value={field.height}
                      onChange={(event) => changeBox({ height: Number(event.target.value) })}
                    />
                  </label>
                </div>
                <label>
                  Cambiar lugar con
                  <select
                    value=""
                    onChange={(event) => {
                      if (!event.target.value) return
                      setPreview(null)
                      setFields(swapBoxes(fields, selected, event.target.value))
                    }}
                  >
                    <option value="">—</option>
                    {otherFields(kind.names, selected).map((name) => (
                      <option key={name} value={name}>
                        {kind.labels[name]}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  Color
                  <input
                    type="color"
                    value={field.colour.toLowerCase()}
                    onChange={(event) => change(selected, { colour: event.target.value.toUpperCase() })}
                  />
                </label>
                <div className="field-row">
                  <label>
                    Alineación
                    <select value={field.align} onChange={(event) => change(selected, { align: event.target.value as Align })}>
                      <option value="left">Izquierda</option>
                      <option value="center">Centro</option>
                      <option value="right">Derecha</option>
                    </select>
                  </label>
                  <label>
                    Fuente
                    <select value={field.font} onChange={(event) => change(selected, { font: event.target.value as Font })}>
                      {FONTS.map((font) => (
                        <option key={font} value={font}>
                          {FONT_LABELS[font]}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {preview && (
        <figure className="preview">
          <img src={preview} alt="Muestra de la QSL" />
          <figcaption className="hint">Muestra generada por el servidor, con datos de ejemplo.</figcaption>
        </figure>
      )}
      {error && (
        <p className="error" role="alert">
          {error}
        </p>
      )}
      <div className="actions">
        <button type="button" onClick={() => void save()} disabled={busy || !fields || !size}>
          {busy ? 'Guardando…' : 'Guardar la plantilla'}
        </button>
        <button type="button" onClick={() => void showPreview()} disabled={busy || !fields || !size}>
          Ver la muestra del servidor
        </button>
        <button type="button" onClick={onCancel} disabled={busy}>
          Cancelar
        </button>
      </div>
    </div>
  )
}

function editorError(failure: unknown, action: string): string {
  if (failure instanceof ApiError && failure.status === 413) return 'La imagen es demasiado grande. El máximo es 10 MB.'
  return changeError(failure, {
    action,
    conflict: `No se pudo ${action}.`,
    notFound: 'La plantilla ya no existe.',
  })
}
