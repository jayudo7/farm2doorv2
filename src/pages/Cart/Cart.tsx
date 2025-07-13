import { useState } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { TrashIcon, PlusIcon, MinusIcon } from '@heroicons/react/24/outline'
import { useCart } from '../../contexts/CartContext'
import { useAuth } from '../../contexts/AuthContext'
import Button from '../../components/UI/Button'
import LoadingSpinner from '../../components/UI/LoadingSpinner'

const Cart = () => {
  const { items, itemCount, isLoading, updateQuantity, removeItem, clearCart } = useCart()
  const { user } = useAuth()
  const [updatingItems, setUpdatingItems] = useState<Set<number>>(new Set())

  const handleUpdateQuantity = async (cartId: number, newQuantity: number) => {
    setUpdatingItems(prev => new Set(prev).add(cartId))
    try {
      await updateQuantity(cartId, newQuantity)
    } finally {
      setUpdatingItems(prev => {
        const next = new Set(prev)
        next.delete(cartId)
        return next
      })
    }
  }

  const handleRemoveItem = async (cartId: number) => {
    if (confirm('Remove this item from your cart?')) {
      await removeItem(cartId)
    }
  }

  const handleClearCart = async () => {
    if (confirm('Are you sure you want to clear your entire cart?')) {
      await clearCart()
    }
  }

  // Group items by seller
  const itemsBySeller = items.reduce((acc, item) => {
    const sellerId = item.product.user_id
    if (!acc[sellerId]) {
      acc[sellerId] = {
        seller_name: item.product.seller_name,
        items: [],
        subtotal: 0
      }
    }
    acc[sellerId].items.push(item)
    acc[sellerId].subtotal += item.product.price * item.quantity
    return acc
  }, {} as Record<number, { seller_name: string; items: typeof items; subtotal: number }>)

  const totalAmount = Object.values(itemsBySeller).reduce((sum, seller) => sum + seller.subtotal, 0)
  const deliveryFee = totalAmount >= 100 ? 0 : totalAmount >= 50 ? 5 : 10
  const grandTotal = totalAmount + deliveryFee

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <LoadingSpinner size="lg" className="text-farm-green" />
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Shopping Cart</h1>
          <nav className="text-sm text-gray-600">
            <Link to="/" className="hover:text-farm-green">Home</Link>
            <span className="mx-2">/</span>
            <span className="text-gray-900">Shopping Cart</span>
          </nav>
        </div>

        {items.length === 0 ? (
          /* Empty Cart */
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <div className="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
              <svg className="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13l-1.5-6m0 0L4 5M7 13h10m0 0l1.5 6M17 13l1.5 6" />
              </svg>
            </div>
            <h3 className="text-xl font-semibold text-gray-900 mb-2">Your cart is empty</h3>
            <p className="text-gray-600 mb-6">Looks like you haven't added any products to your cart yet.</p>
            <Link to="/products">
              <Button size="lg">Start Shopping</Button>
            </Link>
            <div className="mt-4">
              <Link to="/favorites" className="text-farm-green hover:underline">
                View your favorites →
              </Link>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {/* Cart Items */}
            <div className="lg:col-span-2 space-y-6">
              {/* Delivery Info */}
              <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                <h3 className="font-semibold text-green-800 mb-2">🚚 Delivery Information</h3>
                <ul className="text-sm text-green-700 space-y-1">
                  <li>Free delivery on orders over $100</li>
                  <li>$5 delivery fee for orders $50 - $99</li>
                  <li>$10 delivery fee for orders under $50</li>
                  <li>Estimated delivery: 2-5 business days</li>
                </ul>
              </div>

              {/* Cart Actions */}
              <div className="flex justify-between items-center">
                <h2 className="text-xl font-semibold text-gray-900">
                  Cart Items ({itemCount})
                </h2>
                <Button
                  variant="danger"
                  size="sm"
                  onClick={handleClearCart}
                >
                  Clear Cart
                </Button>
              </div>

              {/* Items by Seller */}
              {Object.entries(itemsBySeller).map(([sellerId, sellerData]) => (
                <motion.div
                  key={sellerId}
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
                >
                  <div className="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 className="font-semibold text-gray-900">
                      From {sellerData.seller_name}
                    </h3>
                    <p className="text-sm text-gray-600">
                      Subtotal: ${sellerData.subtotal.toFixed(2)}
                    </p>
                  </div>

                  <div className="p-6 space-y-4">
                    {sellerData.items.map((item) => (
                      <div key={item.id} className="flex items-center space-x-4">
                        <img
                          src={item.product.image || 'https://images.unsplash.com/photo-1546470427-e26264be0b0d?w=400'}
                          alt={item.product.name}
                          className="w-16 h-16 object-cover rounded-lg"
                        />

                        <div className="flex-1">
                          <h4 className="font-medium text-gray-900">{item.product.name}</h4>
                          <p className="text-sm text-gray-600">
                            ${item.product.price.toFixed(2)} each
                          </p>
                        </div>

                        <div className="flex items-center space-x-2">
                          <button
                            onClick={() => handleUpdateQuantity(item.id, item.quantity - 1)}
                            disabled={item.quantity <= 1 || updatingItems.has(item.id)}
                            className="p-1 rounded-full hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            <MinusIcon className="w-4 h-4" />
                          </button>

                          <span className="w-8 text-center font-medium">
                            {updatingItems.has(item.id) ? '...' : item.quantity}
                          </span>

                          <button
                            onClick={() => handleUpdateQuantity(item.id, item.quantity + 1)}
                            disabled={updatingItems.has(item.id)}
                            className="p-1 rounded-full hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            <PlusIcon className="w-4 h-4" />
                          </button>
                        </div>

                        <div className="text-right">
                          <p className="font-medium text-gray-900">
                            ${(item.product.price * item.quantity).toFixed(2)}
                          </p>
                        </div>

                        <button
                          onClick={() => handleRemoveItem(item.id)}
                          className="p-2 text-red-600 hover:bg-red-50 rounded-full"
                        >
                          <TrashIcon className="w-4 h-4" />
                        </button>
                      </div>
                    ))}
                  </div>
                </motion.div>
              ))}
            </div>

            {/* Order Summary */}
            <div className="lg:col-span-1">
              <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-8">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Order Summary</h3>

                <div className="space-y-3 mb-6">
                  <div className="flex justify-between">
                    <span className="text-gray-600">Subtotal</span>
                    <span className="font-medium">${totalAmount.toFixed(2)}</span>
                  </div>

                  <div className="flex justify-between">
                    <span className="text-gray-600">Delivery Fee</span>
                    <span className="font-medium">
                      ${deliveryFee.toFixed(2)}
                      {deliveryFee === 0 && (
                        <span className="text-green-600 text-sm ml-1">(FREE!)</span>
                      )}
                    </span>
                  </div>

                  <div className="border-t border-gray-200 pt-3">
                    <div className="flex justify-between">
                      <span className="text-lg font-semibold text-gray-900">Total</span>
                      <span className="text-lg font-semibold text-farm-green">
                        ${grandTotal.toFixed(2)}
                      </span>
                    </div>
                  </div>
                </div>

                <div className="space-y-3">
                  <Button className="w-full" size="lg">
                    Proceed to Checkout
                  </Button>

                  <Link to="/products" className="block">
                    <Button variant="secondary" className="w-full">
                      Continue Shopping
                    </Button>
                  </Link>
                </div>

                {/* Delivery Estimate */}
                <div className="mt-6 p-4 bg-gray-50 rounded-lg">
                  <h4 className="font-medium text-gray-900 mb-2">📅 Estimated Delivery</h4>
                  <p className="text-sm text-gray-600">
                    {new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toLocaleDateString('en-US', {
                      weekday: 'short',
                      month: 'short',
                      day: 'numeric'
                    })} - {new Date(Date.now() + 5 * 24 * 60 * 60 * 1000).toLocaleDateString('en-US', {
                      weekday: 'short',
                      month: 'short',
                      day: 'numeric'
                    })}
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

export default Cart