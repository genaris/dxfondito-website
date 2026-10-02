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
  if ([...newPassword].length < MIN_PASSWORD_LENGTH) {
    return `La contraseña nueva debe tener ${MIN_PASSWORD_LENGTH} o más caracteres.`
  }
  if (new TextEncoder().encode(newPassword).length > 72) return 'La contraseña nueva es demasiado larga.'
  if (newPassword === currentPassword) return 'La contraseña nueva debe ser distinta de la actual.'
  if (newPassword !== repeated) return 'Las dos contraseñas nuevas no coinciden.'
  return null
}
