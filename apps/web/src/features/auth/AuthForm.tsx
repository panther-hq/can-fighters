import { useState } from 'react'
import { parseValidationError, useLogin, useRegister } from './useAuth'

type Mode = 'login' | 'register'

export function AuthForm() {
  const [mode, setMode] = useState<Mode>('login')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')

  const login = useLogin()
  const register = useRegister()
  const active = mode === 'login' ? login : register
  const { message, fields } = active.isError
    ? parseValidationError(active.error)
    : { message: '', fields: {} as Record<string, string> }

  function submit(e: React.FormEvent) {
    e.preventDefault()
    if (mode === 'login') {
      login.mutate({ email, password })
    } else {
      register.mutate({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
    }
  }

  return (
    <div className="auth">
      <div className="auth__tabs" role="tablist">
        <button
          type="button"
          role="tab"
          aria-selected={mode === 'login'}
          className={mode === 'login' ? 'is-active' : ''}
          onClick={() => setMode('login')}
        >
          Logowanie
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={mode === 'register'}
          className={mode === 'register' ? 'is-active' : ''}
          onClick={() => setMode('register')}
        >
          Rejestracja
        </button>
      </div>

      <form className="auth__form" onSubmit={submit} noValidate>
        {mode === 'register' && (
          <label className="field">
            <span>Nazwa gracza</span>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              autoComplete="nickname"
              required
            />
            {fields.name && <em className="field__error">{fields.name}</em>}
          </label>
        )}

        <label className="field">
          <span>E-mail</span>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            autoComplete="email"
            required
          />
          {fields.email && <em className="field__error">{fields.email}</em>}
        </label>

        <label className="field">
          <span>Hasło</span>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
            required
          />
          {fields.password && <em className="field__error">{fields.password}</em>}
        </label>

        {mode === 'register' && (
          <label className="field">
            <span>Powtórz hasło</span>
            <input
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              autoComplete="new-password"
              required
            />
          </label>
        )}

        {message && <p className="auth__error">{message}</p>}

        <button type="submit" className="btn btn--primary" disabled={active.isPending}>
          {active.isPending
            ? 'Chwileczkę…'
            : mode === 'login'
              ? 'Zaloguj się'
              : 'Utwórz konto'}
        </button>
      </form>
    </div>
  )
}
