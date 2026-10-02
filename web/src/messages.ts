import { ApiError } from './api.ts'

export const MIN_PASSWORD_LENGTH = 8

// The API gives its error messages in English. The user sees these texts.

export function signInError(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 401) return 'El indicativo o la contraseña no son correctos.'
    if (error.status === 423) return 'La cuenta está bloqueada por varios intentos fallidos. Pruebe de nuevo en 15 minutos.'
    if (error.status === 403) return 'La cuenta está desactivada.'
  }
  return 'No se pudo ingresar. Pruebe de nuevo más tarde.'
}

export function passwordChangeError(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 401) return 'La sesión terminó. Ingrese de nuevo.'
    if (error.status === 403) return 'La contraseña actual no es correcta.'
    if (error.status === 422) return 'La contraseña nueva no es válida. Debe tener 8 o más caracteres y ser distinta de la actual.'
  }
  return 'No se pudo cambiar la contraseña. Pruebe de nuevo más tarde.'
}

/**
 * The checks of the browser. The API makes the same checks.
 */
export function newPasswordProblem(currentPassword: string, newPassword: string, repeated: string): string | null {
  const problem = initialPasswordProblem(newPassword)
  if (problem) return problem
  if (newPassword === currentPassword) return 'La contraseña nueva debe ser distinta de la actual.'
  if (newPassword !== repeated) return 'Las dos contraseñas nuevas no coinciden.'
  return null
}

function commonError(error: unknown, action: string): string {
  if (error instanceof ApiError) {
    if (error.status === 401) return 'La sesión terminó. Ingrese de nuevo.'
    if (error.status === 403) return 'Solo un administrador puede hacer esto.'
    if (error.status === 404) return 'La cuenta no existe.'
    if (error.status === 422) return 'Un valor no es válido. Revise los datos.'
  }
  return `No se pudo ${action}. Pruebe de nuevo más tarde.`
}

export function accountCreateError(error: unknown): string {
  if (error instanceof ApiError && error.status === 409) return 'Ese indicativo ya tiene una cuenta.'
  return commonError(error, 'crear la cuenta')
}

export function accountUpdateError(error: unknown): string {
  if (error instanceof ApiError && error.status === 409) {
    return 'Debe quedar por lo menos un administrador activo.'
  }
  return commonError(error, 'guardar los cambios')
}

export function initialPasswordError(error: unknown): string {
  return commonError(error, 'cambiar la contraseña')
}

export function initialPasswordProblem(password: string): string | null {
  if ([...password].length < MIN_PASSWORD_LENGTH) {
    return `La contraseña debe tener ${MIN_PASSWORD_LENGTH} o más caracteres.`
  }
  if (new TextEncoder().encode(password).length > 72) return 'La contraseña es demasiado larga.'
  return null
}

/**
 * The message for a failed change of a reference or an activity.
 * A 409 answer has a different meaning for each action. Thus the caller gives its text.
 */
export function changeError(error: unknown, texts: { action: string; conflict: string; notFound: string }): string {
  if (error instanceof ApiError) {
    if (error.status === 409) return texts.conflict
    if (error.status === 404) return texts.notFound
    if (error.status === 401) return 'La sesión terminó. Ingrese de nuevo.'
    if (error.status === 403) return 'Solo un administrador puede hacer esto.'
    if (error.status === 422) return 'Un valor no es válido. Revise los datos.'
  }
  return `No se pudo ${texts.action}. Pruebe de nuevo más tarde.`
}
