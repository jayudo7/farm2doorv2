import { useState } from 'react'
import { motion } from 'framer-motion'
import { HeartIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../contexts/AuthContext'
import ProductCard from '../../components/Products/ProductCard'
import LoadingSpinner from '../../components/UI/LoadingSpinner'
import Button from '../../components/UI/Button'

const Favorites = () => {
  const { user } = useAuth()
  const [searchQuery, setSearchQuery] = useState('')

  const { data: favorites = [], isLoading } = useQuery({
    queryKey: ['favorites'],
    queryFn: async () => {
      // Mock data - replace with actual API call
      return [
        {
          id: 1,
          product_id: 2,
          product: {
            id: 2,
            name: 'Organic Carrots',
            description: 'Sweet and crunchy organic carrots',
            price: 3.49,
            quantity: 30,
            category: 'Vegetables',
            image: 'https://images.unsplash.com/photo-1445282768818-728615cc910a?w=400',
            user_id: 3,
            seller_name: 'Green Valley Farm',
            location: 'Oregon'
          },
          added_at: '2024-01-14'
        },
        {
          id: 2,
          product_id: 6,
          product: {
            id: 6,
            name: 'Fresh Strawberries',
            description: 'Sweet and juicy strawberries',
            price: 6.99,
            quantity: 20,
            category: 'Fruits',
            image: 'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=400',
            user_id: 6,
            seller_name: 'Berry Farm',
            location: 'Florida'
          },
          added_at: '2024-01-12'
        }
      ]
    }
  })

  const handleToggleFavorite = (productId: number) => {
    // This would typically call an API to remove from favorites
    console.log('Toggle favorite for product:', productId)
  }

  const filteredFavorites = favorites.filter(favorite =>
    favorite.product.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    favorite.product.description.toLowerCase().includes(searchQuery.toLowerCase())
  )

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
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Favorites & Saves</h1>
          <p className="text-gray-600">Welcome back, {user?.first_name}! Here are your saved products.</p>
        </div>

        {/* Stats */}
        <div className="mb-8">
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
              <div>
                <div className="text-2xl font-bold text-farm-green">{favorites.length}</div>
                <div className="text-sm text-gray-600">Total Favorites</div>
              </div>
              <div>
                <div className="text-2xl font-bold text-farm-green">
                  {favorites.filter(f => f.product.quantity > 0).length}
                </div>
                <div className="text-sm text-gray-600">Available Now</div>
              </div>
              <div>
                <div className="text-2xl font-bold text-farm-green">
                  {favorites.filter(f => f.product.quantity === 0).length}
                </div>
                <div className="text-sm text-gray-600">Out of Stock</div>
              </div>
            </div>
          </div>
        </div>

        {/* Search */}
        <div className="mb-8">
          <div className="relative max-w-md">
            <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
            <input
              type="text"
              placeholder="Search your favorites..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green"
            />
          </div>
        </div>

        {/* Favorites Grid */}
        {filteredFavorites.length === 0 ? (
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <div className="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
              <HeartIcon className="w-12 h-12 text-gray-400" />
            </div>
            <h3 className="text-xl font-semibold text-gray-900 mb-2">
              {searchQuery ? 'No favorites found' : 'No favorites yet'}
            </h3>
            <p className="text-gray-600 mb-6">
              {searchQuery 
                ? `No favorites found matching "${searchQuery}". Try searching with different keywords.`
                : 'Start adding products to your favorites from the home page'
              }
            </p>
            {searchQuery ? (
              <Button onClick={() => setSearchQuery('')}>
                Clear Search
              </Button>
            ) : (
              <Button onClick={() => window.location.href = '/products'}>
                Browse Products
              </Button>
            )}
          </div>
        ) : (
          <>
            <div className="mb-6">
              <h2 className="text-xl font-semibold text-gray-900">
                {searchQuery 
                  ? `${filteredFavorites.length} Products Found for "${searchQuery}"`
                  : `${filteredFavorites.length} Products Saved`
                }
              </h2>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              {filteredFavorites.map((favorite, index) => (
                <motion.div
                  key={favorite.id}
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: index * 0.1 }}
                  className="relative"
                >
                  <ProductCard 
                    product={favorite.product}
                    isFavorite={true}
                    onToggleFavorite={handleToggleFavorite}
                  />
                  
                  {/* Favorite Badge */}
                  <div className="absolute top-3 right-3 bg-yellow-400 text-white px-2 py-1 rounded-full text-xs font-medium">
                    ♥ Favorite
                  </div>
                  
                  {/* Added Date */}
                  <div className="mt-2 text-xs text-gray-500 text-center">
                    Added: {new Date(favorite.added_at).toLocaleDateString()}
                  </div>
                </motion.div>
              ))}
            </div>

            {/* Quick Actions */}
            <div className="mt-12 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
              <h3 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
              <div className="flex flex-wrap gap-4 justify-center">
                <Button>
                  Add All Available to Cart ({favorites.filter(f => f.product.quantity > 0).length})
                </Button>
                <Button variant="secondary" onClick={() => window.location.href = '/products'}>
                  Continue Shopping
                </Button>
                <Button variant="secondary" onClick={() => window.location.href = '/cart'}>
                  View Cart
                </Button>
              </div>
            </div>
          </>
        )}

        {/* Recommendations */}
        <div className="mt-12 bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="text-lg font-semibold text-blue-900 mb-2">You Might Also Like</h3>
          <p className="text-blue-700 mb-4">Discover more products similar to your favorites</p>
          <Button variant="secondary" onClick={() => window.location.href = '/products'}>
            Browse Recommendations
          </Button>
        </div>
      </div>
    </div>
  )
}

export default Favorites