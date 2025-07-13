import { motion } from 'framer-motion'
import { HeartIcon, ShoppingCartIcon, MapPinIcon } from '@heroicons/react/24/outline'
import { HeartIcon as HeartSolidIcon } from '@heroicons/react/24/solid'
import Button from '../UI/Button'
import { useCart } from '../../contexts/CartContext'
import { useAuth } from '../../contexts/AuthContext'
import { useState } from 'react'
import toast from 'react-hot-toast'

interface Product {
  id: number
  name: string
  description?: string
  price: number
  quantity: number
  category?: string
  image?: string
  user_id: number
  seller_name: string
  location?: string
}

interface ProductCardProps {
  product: Product
  isFavorite?: boolean
  onToggleFavorite?: (productId: number) => void
  showActions?: boolean
}

const ProductCard = ({ 
  product, 
  isFavorite = false, 
  onToggleFavorite, 
  showActions = true 
}: ProductCardProps) => {
  const [quantity, setQuantity] = useState(1)
  const [isLoading, setIsLoading] = useState(false)
  const { addToCart } = useCart()
  const { isAuthenticated, user } = useAuth()

  const handleAddToCart = async () => {
    if (!isAuthenticated) {
      toast.error('Please sign in to add items to cart')
      return
    }

    if (user?.id === product.user_id) {
      toast.error('You cannot buy your own products')
      return
    }

    if (quantity > product.quantity) {
      toast.error(`Only ${product.quantity} items available`)
      return
    }

    setIsLoading(true)
    try {
      await addToCart(product.id, quantity)
    } finally {
      setIsLoading(false)
    }
  }

  const handleToggleFavorite = () => {
    if (!isAuthenticated) {
      toast.error('Please sign in to add favorites')
      return
    }

    if (user?.id === product.user_id) {
      toast.error('You cannot favorite your own products')
      return
    }

    onToggleFavorite?.(product.id)
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      whileHover={{ y: -5 }}
      className="card-hover group"
    >
      {/* Image */}
      <div className="relative aspect-w-16 aspect-h-9 overflow-hidden">
        <img
          src={product.image || 'https://images.unsplash.com/photo-1546470427-e26264be0b0d?w=400'}
          alt={product.name}
          className="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300"
        />
        
        {/* Favorite Button */}
        {showActions && isAuthenticated && user?.id !== product.user_id && (
          <button
            onClick={handleToggleFavorite}
            className="absolute top-3 right-3 p-2 bg-white/80 backdrop-blur-sm rounded-full hover:bg-white transition-colors duration-200"
          >
            {isFavorite ? (
              <HeartSolidIcon className="w-5 h-5 text-red-500" />
            ) : (
              <HeartIcon className="w-5 h-5 text-gray-600" />
            )}
          </button>
        )}

        {/* Stock Badge */}
        <div className="absolute top-3 left-3">
          {product.quantity > 0 ? (
            <span className="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
              {product.quantity} available
            </span>
          ) : (
            <span className="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">
              Out of stock
            </span>
          )}
        </div>
      </div>

      {/* Content */}
      <div className="p-4">
        <div className="mb-3">
          <h3 className="text-lg font-semibold text-gray-900 mb-1 line-clamp-1">
            {product.name}
          </h3>
          <p className="text-2xl font-bold text-farm-green mb-2">
            ${product.price.toFixed(2)}
          </p>
          
          {product.location && (
            <div className="flex items-center text-sm text-gray-600 mb-2">
              <MapPinIcon className="w-4 h-4 mr-1" />
              {product.location}
            </div>
          )}
          
          <p className="text-sm text-gray-600">
            Seller: {product.seller_name}
          </p>
          
          {product.category && (
            <span className="inline-block mt-2 px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">
              {product.category}
            </span>
          )}
        </div>

        {/* Actions */}
        {showActions && product.quantity > 0 && isAuthenticated && user?.id !== product.user_id && (
          <div className="space-y-3">
            <div className="flex items-center space-x-2">
              <label className="text-sm font-medium text-gray-700">Qty:</label>
              <input
                type="number"
                min="1"
                max={product.quantity}
                value={quantity}
                onChange={(e) => setQuantity(Math.max(1, Math.min(product.quantity, parseInt(e.target.value) || 1)))}
                className="w-16 px-2 py-1 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green"
              />
            </div>
            
            <Button
              onClick={handleAddToCart}
              loading={isLoading}
              className="w-full"
              size="sm"
            >
              <ShoppingCartIcon className="w-4 h-4 mr-2" />
              Add to Cart
            </Button>
          </div>
        )}

        {!isAuthenticated && showActions && (
          <div className="mt-3">
            <p className="text-sm text-gray-500 text-center">
              <a href="/signin" className="text-farm-green hover:underline">
                Sign in
              </a>{' '}
              to purchase
            </p>
          </div>
        )}

        {user?.id === product.user_id && (
          <div className="mt-3 text-center">
            <span className="text-sm text-gray-500 italic">Your product</span>
          </div>
        )}
      </div>
    </motion.div>
  )
}

export default ProductCard