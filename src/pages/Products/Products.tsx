import { useState } from 'react'
import { motion } from 'framer-motion'
import { MagnifyingGlassIcon, FunnelIcon } from '@heroicons/react/24/outline'
import { useQuery } from '@tanstack/react-query'
import ProductCard from '../../components/Products/ProductCard'
import LoadingSpinner from '../../components/UI/LoadingSpinner'
import Button from '../../components/UI/Button'

const Products = () => {
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedCategory, setSelectedCategory] = useState('')
  const [sortBy, setSortBy] = useState('created_at')
  const [showFilters, setShowFilters] = useState(false)

  const { data: products = [], isLoading } = useQuery({
    queryKey: ['products', searchQuery, selectedCategory, sortBy],
    queryFn: async () => {
      // Mock data - replace with actual API call
      return [
        {
          id: 1,
          name: 'Fresh Tomatoes',
          description: 'Organic red tomatoes, perfect for salads',
          price: 5.99,
          quantity: 50,
          category: 'Vegetables',
          image: 'https://images.unsplash.com/photo-1546470427-e26264be0b0d?w=400',
          user_id: 2,
          seller_name: 'Farm Fresh Co.',
          location: 'California',
          created_at: '2024-01-15',
        },
        {
          id: 2,
          name: 'Organic Carrots',
          description: 'Sweet and crunchy organic carrots',
          price: 3.49,
          quantity: 30,
          category: 'Vegetables',
          image: 'https://images.unsplash.com/photo-1445282768818-728615cc910a?w=400',
          user_id: 3,
          seller_name: 'Green Valley Farm',
          location: 'Oregon',
          created_at: '2024-01-14',
        },
        {
          id: 3,
          name: 'Fresh Apples',
          description: 'Crisp and sweet red apples',
          price: 4.99,
          quantity: 25,
          category: 'Fruits',
          image: 'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=400',
          user_id: 2,
          seller_name: 'Farm Fresh Co.',
          location: 'Washington',
          created_at: '2024-01-13',
        },
        {
          id: 4,
          name: 'Organic Spinach',
          description: 'Fresh organic spinach leaves',
          price: 2.99,
          quantity: 40,
          category: 'Vegetables',
          image: 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=400',
          user_id: 4,
          seller_name: 'Organic Gardens',
          location: 'Vermont',
          created_at: '2024-01-12',
        },
        {
          id: 5,
          name: 'Sweet Corn',
          description: 'Fresh sweet corn on the cob',
          price: 1.99,
          quantity: 60,
          category: 'Vegetables',
          image: 'https://images.unsplash.com/photo-1551754655-cd27e38d2076?w=400',
          user_id: 5,
          seller_name: 'Corn Valley Farm',
          location: 'Iowa',
          created_at: '2024-01-11',
        },
        {
          id: 6,
          name: 'Fresh Strawberries',
          description: 'Sweet and juicy strawberries',
          price: 6.99,
          quantity: 20,
          category: 'Fruits',
          image: 'https://images.unsplash.com/photo-1464965911861-746a04b4bca6?w=400',
          user_id: 6,
          seller_name: 'Berry Farm',
          location: 'Florida',
          created_at: '2024-01-10',
        }
      ]
    }
  })

  const { data: categories = [] } = useQuery({
    queryKey: ['categories'],
    queryFn: async () => {
      return [
        { category: 'Vegetables', count: 15 },
        { category: 'Fruits', count: 12 },
        { category: 'Meat', count: 8 },
        { category: 'Dairy', count: 6 },
        { category: 'Grains', count: 10 },
        { category: 'Fish', count: 5 },
      ]
    }
  })

  const filteredProducts = products.filter(product => {
    const matchesSearch = product.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                         product.description.toLowerCase().includes(searchQuery.toLowerCase())
    const matchesCategory = !selectedCategory || product.category === selectedCategory
    return matchesSearch && matchesCategory
  })

  const sortedProducts = [...filteredProducts].sort((a, b) => {
    switch (sortBy) {
      case 'price_low':
        return a.price - b.price
      case 'price_high':
        return b.price - a.price
      case 'name':
        return a.name.localeCompare(b.name)
      default:
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    }
  })

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-4">All Products</h1>
          
          {/* Search and Filters */}
          <div className="flex flex-col lg:flex-row gap-4">
            {/* Search Bar */}
            <div className="flex-1 relative">
              <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
              <input
                type="text"
                placeholder="Search products..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green"
              />
            </div>

            {/* Filter Toggle */}
            <Button
              variant="secondary"
              onClick={() => setShowFilters(!showFilters)}
              className="lg:hidden flex items-center"
            >
              <FunnelIcon className="w-4 h-4 mr-2" />
              Filters
            </Button>

            {/* Desktop Filters */}
            <div className="hidden lg:flex gap-4">
              <select
                value={selectedCategory}
                onChange={(e) => setSelectedCategory(e.target.value)}
                className="px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green"
              >
                <option value="">All Categories</option>
                {categories.map((cat) => (
                  <option key={cat.category} value={cat.category}>
                    {cat.category} ({cat.count})
                  </option>
                ))}
              </select>

              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green"
              >
                <option value="created_at">Newest First</option>
                <option value="price_low">Price: Low to High</option>
                <option value="price_high">Price: High to Low</option>
                <option value="name">Name: A to Z</option>
              </select>
            </div>
          </div>

          {/* Mobile Filters */}
          {showFilters && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: 'auto' }}
              exit={{ opacity: 0, height: 0 }}
              className="lg:hidden mt-4 p-4 bg-white rounded-lg border border-gray-200 space-y-4"
            >
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <select
                  value={selectedCategory}
                  onChange={(e) => setSelectedCategory(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green"
                >
                  <option value="">All Categories</option>
                  {categories.map((cat) => (
                    <option key={cat.category} value={cat.category}>
                      {cat.category} ({cat.count})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                <select
                  value={sortBy}
                  onChange={(e) => setSortBy(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green"
                >
                  <option value="created_at">Newest First</option>
                  <option value="price_low">Price: Low to High</option>
                  <option value="price_high">Price: High to Low</option>
                  <option value="name">Name: A to Z</option>
                </select>
              </div>
            </motion.div>
          )}
        </div>

        {/* Results Info */}
        <div className="mb-6">
          <p className="text-gray-600">
            Showing {sortedProducts.length} of {products.length} products
            {searchQuery && ` for "${searchQuery}"`}
            {selectedCategory && ` in ${selectedCategory}`}
          </p>
        </div>

        {/* Products Grid */}
        {isLoading ? (
          <div className="flex justify-center py-12">
            <LoadingSpinner size="lg" className="text-farm-green" />
          </div>
        ) : sortedProducts.length === 0 ? (
          <div className="text-center py-12">
            <div className="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
              <MagnifyingGlassIcon className="w-12 h-12 text-gray-400" />
            </div>
            <h3 className="text-xl font-semibold text-gray-900 mb-2">No products found</h3>
            <p className="text-gray-600 mb-6">
              {searchQuery || selectedCategory
                ? 'Try adjusting your search criteria or browse all products'
                : 'No products are available at the moment'
              }
            </p>
            {(searchQuery || selectedCategory) && (
              <Button
                onClick={() => {
                  setSearchQuery('')
                  setSelectedCategory('')
                }}
              >
                Clear Filters
              </Button>
            )}
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {sortedProducts.map((product, index) => (
              <motion.div
                key={product.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
              >
                <ProductCard product={product} />
              </motion.div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default Products