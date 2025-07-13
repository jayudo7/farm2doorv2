import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react'
import { cartAPI } from '../services/api'
import { useAuth } from './AuthContext'
import toast from 'react-hot-toast'

interface CartItem {
  id: number
  product_id: number
  quantity: number
  product: {
    id: number
    name: string
    price: number
    image?: string
    user_id: number
    seller_name: string
  }
}

interface CartContextType {
  items: CartItem[]
  itemCount: number
  isLoading: boolean
  addToCart: (productId: number, quantity: number) => Promise<void>
  updateQuantity: (cartId: number, quantity: number) => Promise<void>
  removeItem: (cartId: number) => Promise<void>
  clearCart: () => Promise<void>
  refreshCart: () => Promise<void>
}

const CartContext = createContext<CartContextType | undefined>(undefined)

export const useCart = () => {
  const context = useContext(CartContext)
  if (context === undefined) {
    throw new Error('useCart must be used within a CartProvider')
  }
  return context
}

interface CartProviderProps {
  children: ReactNode
}

export const CartProvider: React.FC<CartProviderProps> = ({ children }) => {
  const [items, setItems] = useState<CartItem[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const { isAuthenticated } = useAuth()

  const refreshCart = async () => {
    if (!isAuthenticated) {
      setItems([])
      return
    }

    try {
      setIsLoading(true)
      const cartItems = await cartAPI.getCart()
      setItems(cartItems)
    } catch (error) {
      console.error('Failed to fetch cart:', error)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    refreshCart()
  }, [isAuthenticated])

  const addToCart = async (productId: number, quantity: number) => {
    try {
      await cartAPI.addToCart(productId, quantity)
      await refreshCart()
      toast.success('Added to cart!')
    } catch (error: any) {
      toast.error(error.message || 'Failed to add to cart')
    }
  }

  const updateQuantity = async (cartId: number, quantity: number) => {
    try {
      await cartAPI.updateQuantity(cartId, quantity)
      await refreshCart()
      toast.success('Cart updated!')
    } catch (error: any) {
      toast.error(error.message || 'Failed to update cart')
    }
  }

  const removeItem = async (cartId: number) => {
    try {
      await cartAPI.removeItem(cartId)
      await refreshCart()
      toast.success('Item removed from cart')
    } catch (error: any) {
      toast.error(error.message || 'Failed to remove item')
    }
  }

  const clearCart = async () => {
    try {
      await cartAPI.clearCart()
      setItems([])
      toast.success('Cart cleared')
    } catch (error: any) {
      toast.error(error.message || 'Failed to clear cart')
    }
  }

  const itemCount = items.reduce((total, item) => total + item.quantity, 0)

  const value = {
    items,
    itemCount,
    isLoading,
    addToCart,
    updateQuantity,
    removeItem,
    clearCart,
    refreshCart,
  }

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}