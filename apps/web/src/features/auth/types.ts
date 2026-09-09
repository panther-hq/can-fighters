export interface PlayerProfile {
  level: number
  xp: number
  coins: number
  rating: number
}

export interface AuthUser {
  id: number
  name: string
  email: string
  profile: PlayerProfile
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface RegisterCredentials {
  name: string
  email: string
  password: string
  password_confirmation: string
}

/** Laravel 422 validation error body. */
export interface ValidationErrorBody {
  message: string
  errors: Record<string, string[]>
}
