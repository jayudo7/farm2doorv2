import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react'
import { authAPI } from '../services/api'
import toast from 'react-hot-toast'

interface User {
  id: number
  first_name: string
  last_name: string
  email: string
  user_type: 'buyer' | 'seller'
  location?: string
  phone?: string
}

interface AuthContextType {
  user: User | null
  isLoading: boolean
  isAuthenticated: boolean
  login: (email: string, password: string) => Promise<boolean>
  logout: () => void
  register: (userData: any) => Promise<boolean>
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

export const useAuth = () => {
  const context = useContext(AuthContext)
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}

interface AuthProviderProps {
  children: ReactNode
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    // Check if user is logged in on app start
    const checkAuth = async () => {
      try {
        const token = localStorage.getItem('auth_token')
        if (token) {
          const userData = await authAPI.getProfile()
          setUser(userData)
        }
      } catch (error) {
        localStorage.removeItem('auth_token')
      } finally {
        setIsLoading(false)
      }
    }

    checkAuth()
  }, [])

  const login = async (email: string, password: string): Promise<boolean> => {
    try {
      setIsLoading(true)
      const response = await authAPI.login(email, password)
      
      if (response.success) {
        localStorage.setItem('auth_token', response.token)
        setUser(response.user)
        toast.success('Welcome back!')
        return true
      } else {
        toast.error(response.message || 'Login failed')
        return false
      }
    } catch (error: any) {
      toast.error(error.message || 'Login failed')
      return false
    } finally {
      setIsLoading(false)
    }
  }

  const register = async (userData: any): Promise<boolean> => {
    try {
      setIsLoading(true)
      const response = await authAPI.register(userData)
      
      if (response.success) {
        localStorage.setItem('auth_token', response.token)
        setUser(response.user)
        toast.success('Account created successfully!')
        return true
      } else {
        toast.error(response.message || 'Registration failed')
        return false
      }
    } catch (error: any) {
      toast.error(error.message || 'Registration failed')
      return false
    } finally {
      setIsLoading(false)
    }
  }

  const logout = () => {
    localStorage.removeItem('auth_token')
    setUser(null)
    toast.success('Logged out successfully')
  }

  const value = {
    user,
    isLoading,
    isAuthenticated: !!user,
    login,
    logout,
    register,
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}