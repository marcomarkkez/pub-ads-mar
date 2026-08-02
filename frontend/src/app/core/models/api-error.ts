import { HttpErrorResponse } from '@angular/common/http';

// Mirrors backend/app/Enums/ApiErrorCode.php. NETWORK_ERROR has no backend
// counterpart: it means the request never reached the server at all (CORS
// rejection, wrong API URL, connection refused), as opposed to the server
// responding with an actual error.
export enum ApiErrorCode {
  AuthInvalidCredentials = 'AUTH_INVALID_CREDENTIALS',
  AuthAccountDeactivated = 'AUTH_ACCOUNT_DEACTIVATED',
  Unauthenticated = 'UNAUTHENTICATED',
  Forbidden = 'FORBIDDEN',
  ValidationFailed = 'VALIDATION_FAILED',
  NotFound = 'NOT_FOUND',
  ServerError = 'SERVER_ERROR',
  NetworkError = 'NETWORK_ERROR',
}

export interface ApiError {
  code: ApiErrorCode | string;
  message: string;
}

export function parseApiError(err: HttpErrorResponse): ApiError {
  if (err.status === 0) {
    return {
      code: ApiErrorCode.NetworkError,
      message: 'Could not reach the API. Check the backend URL, CORS, and that it is running.',
    };
  }

  const code = err.error?.error_code;
  const message = err.error?.message;
  if (code && message) {
    return { code, message };
  }

  return {
    code: ApiErrorCode.ServerError,
    message: message || `Unexpected error (HTTP ${err.status}).`,
  };
}
