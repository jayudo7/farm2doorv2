import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { MagnifyingGlassIcon, SparklesIcon } from '@heroicons/react/24/outline'
import { useQuery } from '@tanstack/react-query'
import { productsAPI } from '../services/api'
import ProductCard from '../components/Products/ProductCard'
import LoadingSpinner from '../components/UI/LoadingSpinner'
import Button from '../components/UI/Button'
import { useAuth } from '../contexts/AuthContext'

const Home = () => {
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedCategory, setSelectedCategory] = useState('')
  const { user, isAuthenticated } = useAuth()

  const { data: products = [], isLoading: productsLoading } = useQuery({
    queryKey: ['products', searchQuery, selectedCategory],
    queryFn: () => productsAPI.getProducts({ search: searchQuery, category: selectedCategory }),
  })

  const { data: categories = [] } = useQuery({
    queryKey: ['categories'],
    queryFn: () => productsAPI.getCategories(),
  })

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    // Search is handled by the query key change
  }

  const categoryImages = {
    'Vegetables': 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=300',
    'Fruits': 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=300',
    'Meat': 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?w=300',
    'Dairy': 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=300',
    'Grains': 'https://images.unsplash.com/photo-1574323347407-f5e1ad6d020b?w=300',
    'Fish': 'https://images.unsplash.com/photo-1544943910-4c1dc44aab44?w=300',
  }

  return (
    <div className="min-h-screen">
      {/* Hero Section */}
      <section className="relative bg-gradient-to-br from-farm-green via-green-600 to-green-700 text-white overflow-hidden">
        <div className="absolute inset-0 bg-black/20" />
        <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8 }}
            className="text-center"
          >
            <h1 className="text-4xl md:text-6xl font-bold mb-6 text-balance">
              {isAuthenticated ? (
                <>Welcome back, {user?.first_name}! <br />What are you looking for?</>
              ) : (
                <>Fresh Farm Produce <br />Delivered to Your Door</>
              )}
            </h1>
            <p className="text-xl md:text-2xl mb-8 text-green-100 max-w-3xl mx-auto text-balance">
              Connect directly with local farmers and get the freshest produce delivered straight to your doorstep
            </p>

            {/* Search Bar */}
            <motion.form
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.8, delay: 0.2 }}
              onSubmit={handleSearch}
              className="max-w-2xl mx-auto flex flex-col sm:flex-row gap-4"
            >
              <div className="flex-1 relative">
                <MagnifyingGlassIcon className="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  type="text"
                  placeholder="Search for fresh produce..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full pl-12 pr-4 py-4 rounded-xl text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-4 focus:ring-white/30 text-lg"
                />
              </div>
              <select
                value={selectedCategory}
                onChange={(e) => setSelectedCategory(e.target.value)}
                className="px-6 py-4 rounded-xl text-gray-900 focus:outline-none focus:ring-4 focus:ring-white/30 text-lg"
              >
                <option value="">All Categories</option>
                {categories.map((cat: any) => (
                  <option key={cat.category} value={cat.category}>
                    {cat.category} ({cat.count})
                  </option>
                ))}
              </select>
              <Button
                type="submit"
                variant="secondary"
                size="lg"
                className="px-8 py-4 text-lg font-semibold"
              >
                Search
              </Button>
            </motion.form>
          </motion.div>
        </div>

        {/* Decorative elements */}
        <div className="absolute top-20 left-10 w-20 h-20 bg-white/10 rounded-full animate-bounce-gentle" />
        <div className="absolute bottom-20 right-10 w-16 h-16 bg-white/10 rounded-full animate-bounce-gentle" style={{ animationDelay: '1s' }} />
      </section>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        {/* Categories Section */}
        <motion.section
          initial={{ opacity: 0, y: 30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, delay: 0.4 }}
          className="mb-16"
        >
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-gray-900 mb-4">Shop by Category</h2>
            <p className="text-lg text-gray-600">Discover fresh produce organized by category</p>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
            {categories.map((category: any, index: number) => (
              <motion.button
                key={category.category}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                onClick={() => setSelectedCategory(category.category)}
                className="group relative overflow-hidden rounded-xl aspect-square bg-white shadow-sm hover:shadow-lg transition-all duration-300"
              >
                <img
                  src={categoryImages[category.category as keyof typeof categoryImages] || 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=300'}
                  alt={category.category}
                  className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                <div className="absolute bottom-0 left-0 right-0 p-4 text-white">
                  <h3 className="font-semibold text-sm md:text-base">{category.category}</h3>
                  <p className="text-xs opacity-90">{category.count} products</p>
                </div>
              </motion.button>
            ))}
          </div>
        </motion.section>

        {/* Products Section */}
        <motion.section
          initial={{ opacity: 0, y: 30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, delay: 0.6 }}
        >
          <div className="flex items-center justify-between mb-8">
            <div>
              <h2 className="text-3xl font-bold text-gray-900 mb-2">
                {searchQuery || selectedCategory ? (
                  `Search Results (${products.length} found)`
                ) : (
                  `Latest Products (${products.length} items)`
                )}
              </h2>
              {(searchQuery || selectedCategory) && (
                <div className="flex items-center gap-2 text-sm text-gray-600">
                  {searchQuery && <span>Search: "{searchQuery}"</span>}
                  {selectedCategory && <span>Category: {selectedCategory}</span>}
                  <button
                    onClick={() => {
                      setSearchQuery('')
                      setSelectedCategory('')
                    }}
                    className="text-farm-green hover:underline ml-2"
                  >
                    Clear filters
                  </button>
                </div>
              )}
            </div>
            
            <Link to="/products">
              <Button variant="ghost" className="flex items-center">
                View All
                <SparklesIcon className="w-4 h-4 ml-2" />
              </Button>
            </Link>
          </div>

          {productsLoading ? (
            <div className="flex justify-center py-12">
              <LoadingSpinner size="lg" className="text-farm-green" />
            </div>
          ) : products.length === 0 ? (
            <div className="text-center py-12">
              <div className="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                <MagnifyingGlassIcon className="w-12 h-12 text-gray-400" />
              </div>
              <h3 className="text-xl font-semibold text-gray-900 mb-2">No products found</h3>
              <p className="text-gray-600 mb-6">
                {searchQuery || selectedCategory 
                  ? 'Try adjusting your search criteria or browse all products'
                  : 'Be the first to add products to Farm2Door!'
                }
              </p>
              {isAuthenticated ? (
                <Link to="/dashboard">
                  <Button>Add Your Products</Button>
                </Link>
              ) : (
                <Link to="/signup">
                  <Button>Sign Up to Sell</Button>
                </Link>
              )}
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              {products.map((product: any, index: number) => (
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
        </motion.section>

        {/* CTA Section */}
        {!isAuthenticated && (
          <motion.section
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8, delay: 0.8 }}
            className="mt-20 text-center"
          >
            <div className="bg-gradient-to-r from-farm-green to-green-600 rounded-2xl p-12 text-white">
              <h2 className="text-3xl font-bold mb-4">Ready to Get Started?</h2>
              <p className="text-xl mb-8 text-green-100">
                Join thousands of customers enjoying fresh, local produce
              </p>
              <div className="flex flex-col sm:flex-row gap-4 justify-center">
                <Link to="/signup">
                  <Button variant="secondary" size="lg" className="px-8">
                    Sign Up as Buyer
                  </Button>
                </Link>
                <Link to="/signup">
                  <Button variant="ghost" size="lg" className="px-8 text-white border-white hover:bg-white/10">
                    Become a Seller
                  </Button>
                </Link>
              </div>
            </div>
          </motion.section>
        )}
      </div>
    </div>
  )
}

export default Home