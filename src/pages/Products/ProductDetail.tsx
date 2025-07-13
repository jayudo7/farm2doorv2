import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { 
  HeartIcon, 
  ShoppingCartIcon, 
  MapPinIcon,
  StarIcon,
  ChevronLeftIcon
} from '@heroicons/react/24/outline'
import { HeartIcon as HeartSolidIcon } from '@heroicons/react/24/solid'
import { useQuery } from '@tanstack/react-query'
import { useCart } from '../../contexts/CartContext'
import { useAuth } from '../../contexts/AuthContext'
import Button from '../../components/UI/Button'
import ProductCard from '../../components/Products/ProductCard'
import LoadingSpinner from '../../components/UI/LoadingSpinner'
import toast from 'react-hot-toast'

const ProductDetail = () => {
  const { id } = useParams<{ id: string }>()
  const [quantity, setQuantity] = useState(1)
  const [isFavorite, setIsFavorite] = useState(false)
  const [selectedImage, setSelectedImage] = useState(0)
  const { addToCart } = useCart()
  const { isAuthenticated, user } = useAuth()

  const { data: product, isLoading } = useQuery({
    queryKey: ['product', id],
    queryFn: async () => {
      // Mock data - replace with actual API call
      return {
        id: parseInt(id || '1'),
        name: 'Fresh Organic Tomatoes',
        description: 'Premium organic tomatoes grown without pesticides in our sustainable farm. Perfect for salads, cooking, and making fresh sauces. These tomatoes are vine-ripened for maximum flavor and nutritional value.',
        price: 5.99,
        quantity: 50,
        category: 'Vegetables',
        images: [
          'https://images.unsplash.com/photo-1546470427-e26264be0b0d?w=800',
          'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800',
          'https://images.unsplash.com/photo-1518977676601-b53f82aba655?w=800'
        ],
        user_id: 2,
        seller: {
          id: 2,
          first_name: 'Jane',
          last_name: 'Smith',
          email: 'jane@farmfresh.com',
          location: 'California',
          farm_name: 'Farm Fresh Co.',
          rating: 4.8,
          total_reviews: 127
        },
        created_at: '2024-01-15',
        specifications: {
          'Organic Certified': 'Yes',
          'Harvest Date': 'January 10, 2024',
          'Storage': 'Refrigerate for best quality',
          'Shelf Life': '7-10 days'
        }
      }
    }
  })

  const { data: similarProducts = [] } = useQuery({
    queryKey: ['similar-products', product?.category],
    queryFn: async () => {
      // Mock data - replace with actual API call
      return [
        {
          id: 2,
          name: 'Organic Carrots',
          price: 3.49,
          quantity: 30,
          category: 'Vegetables',
          image: 'https://images.unsplash.com/photo-1445282768818-728615cc910a?w=400',
          user_id: 3,
          seller_name: 'Green Valley Farm',
          location: 'Oregon'
        },
        {
          id: 4,
          name: 'Organic Spinach',
          price: 2.99,
          quantity: 40,
          category: 'Vegetables',
          image: 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=400',
          user_id: 4,
          seller_name: 'Organic Gardens',
          location: 'Vermont'
        }
      ]
    },
    enabled: !!product
  })

  const handleAddToCart = async () => {
    if (!isAuthenticated) {
      toast.error('Please sign in to add items to cart')
      return
    }

    if (user?.id === product?.user_id) {
      toast.error('You cannot buy your own products')
      return
    }

    if (quantity > (product?.quantity || 0)) {
      toast.error(`Only ${product?.quantity} items available`)
      return
    }

    try {
      await addToCart(product!.id, quantity)
    } catch (error) {
      console.error('Failed to add to cart:', error)
    }
  }

  const handleToggleFavorite = () => {
    if (!isAuthenticated) {
      toast.error('Please sign in to add favorites')
      return
    }

    if (user?.id === product?.user_id) {
      toast.error('You cannot favorite your own products')
      return
    }

    setIsFavorite(!isFavorite)
    toast.success(isFavorite ? 'Removed from favorites' : 'Added to favorites')
  }

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <LoadingSpinner size="lg" className="text-farm-green" />
      </div>
    )
  }

  if (!product) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <h2 className="text-2xl font-bold text-gray-900 mb-4">Product not found</h2>
          <Link to="/products">
            <Button>Back to Products</Button>
          </Link>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Breadcrumb */}
        <nav className="flex items-center space-x-2 text-sm text-gray-600 mb-8">
          <Link to="/" className="hover:text-farm-green">Home</Link>
          <span>/</span>
          <Link to="/products" className="hover:text-farm-green">Products</Link>
          <span>/</span>
          <span className="text-gray-900">{product.name}</span>
        </nav>

        {/* Back Button */}
        <Link to="/products" className="inline-flex items-center text-farm-green hover:text-green-600 mb-6">
          <ChevronLeftIcon className="w-4 h-4 mr-1" />
          Back to Products
        </Link>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12">
          {/* Product Images */}
          <div className="space-y-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="aspect-square overflow-hidden rounded-lg bg-gray-100"
            >
              <img
                src={product.images[selectedImage]}
                alt={product.name}
                className="w-full h-full object-cover"
              />
            </motion.div>

            {/* Image Thumbnails */}
            {product.images.length > 1 && (
              <div className="flex space-x-2">
                {product.images.map((image, index) => (
                  <button
                    key={index}
                    onClick={() => setSelectedImage(index)}
                    className={`w-20 h-20 rounded-lg overflow-hidden border-2 ${
                      selectedImage === index ? 'border-farm-green' : 'border-gray-200'
                    }`}
                  >
                    <img
                      src={image}
                      alt={`${product.name} ${index + 1}`}
                      className="w-full h-full object-cover"
                    />
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Product Info */}
          <div className="space-y-6">
            <div>
              <h1 className="text-3xl font-bold text-gray-900 mb-2">{product.name}</h1>
              <div className="flex items-center space-x-4 mb-4">
                <span className="text-3xl font-bold text-farm-green">
                  ${product.price.toFixed(2)}
                </span>
                {product.quantity > 0 ? (
                  <span className="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                    {product.quantity} available
                  </span>
                ) : (
                  <span className="px-3 py-1 bg-red-100 text-red-800 text-sm font-medium rounded-full">
                    Out of stock
                  </span>
                )}
              </div>
            </div>

            {/* Seller Info */}
            <div className="bg-white rounded-lg p-4 border border-gray-200">
              <h3 className="font-semibold text-gray-900 mb-2">Seller Information</h3>
              <div className="flex items-center space-x-3">
                <div className="w-12 h-12 bg-farm-green rounded-full flex items-center justify-center">
                  <span className="text-white font-bold">
                    {product.seller.first_name[0]}{product.seller.last_name[0]}
                  </span>
                </div>
                <div>
                  <p className="font-medium text-gray-900">
                    {product.seller.first_name} {product.seller.last_name}
                  </p>
                  <p className="text-sm text-gray-600">{product.seller.farm_name}</p>
                  <div className="flex items-center space-x-1">
                    <MapPinIcon className="w-4 h-4 text-gray-400" />
                    <span className="text-sm text-gray-600">{product.seller.location}</span>
                  </div>
                  <div className="flex items-center space-x-1 mt-1">
                    <div className="flex items-center">
                      {[...Array(5)].map((_, i) => (
                        <StarIcon
                          key={i}
                          className={`w-4 h-4 ${
                            i < Math.floor(product.seller.rating)
                              ? 'text-yellow-400 fill-current'
                              : 'text-gray-300'
                          }`}
                        />
                      ))}
                    </div>
                    <span className="text-sm text-gray-600">
                      {product.seller.rating} ({product.seller.total_reviews} reviews)
                    </span>
                  </div>
                </div>
              </div>
            </div>

            {/* Description */}
            <div>
              <h3 className="font-semibold text-gray-900 mb-2">Description</h3>
              <p className="text-gray-600 leading-relaxed">{product.description}</p>
            </div>

            {/* Specifications */}
            <div>
              <h3 className="font-semibold text-gray-900 mb-2">Product Details</h3>
              <div className="bg-gray-50 rounded-lg p-4">
                <dl className="grid grid-cols-1 gap-2">
                  {Object.entries(product.specifications).map(([key, value]) => (
                    <div key={key} className="flex justify-between">
                      <dt className="text-sm font-medium text-gray-600">{key}:</dt>
                      <dd className="text-sm text-gray-900">{value}</dd>
                    </div>
                  ))}
                </dl>
              </div>
            </div>

            {/* Actions */}
            {product.quantity > 0 && isAuthenticated && user?.id !== product.user_id && (
              <div className="space-y-4">
                <div className="flex items-center space-x-4">
                  <label className="text-sm font-medium text-gray-700">Quantity:</label>
                  <input
                    type="number"
                    min="1"
                    max={product.quantity}
                    value={quantity}
                    onChange={(e) => setQuantity(Math.max(1, Math.min(product.quantity, parseInt(e.target.value) || 1)))}
                    className="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green"
                  />
                </div>

                <div className="flex space-x-4">
                  <Button
                    onClick={handleAddToCart}
                    className="flex-1 flex items-center justify-center"
                    size="lg"
                  >
                    <ShoppingCartIcon className="w-5 h-5 mr-2" />
                    Add to Cart
                  </Button>
                  
                  <Button
                    variant="secondary"
                    onClick={handleToggleFavorite}
                    className="flex items-center justify-center"
                    size="lg"
                  >
                    {isFavorite ? (
                      <HeartSolidIcon className="w-5 h-5 text-red-500" />
                    ) : (
                      <HeartIcon className="w-5 h-5" />
                    )}
                  </Button>
                </div>
              </div>
            )}

            {!isAuthenticated && (
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p className="text-blue-800">
                  <Link to="/signin" className="font-medium hover:underline">
                    Sign in
                  </Link>{' '}
                  to purchase this product
                </p>
              </div>
            )}

            {user?.id === product.user_id && (
              <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p className="text-gray-600">This is your product</p>
                <Link to="/dashboard" className="text-farm-green hover:underline">
                  Edit in Dashboard →
                </Link>
              </div>
            )}
          </div>
        </div>

        {/* Similar Products */}
        {similarProducts.length > 0 && (
          <div>
            <h2 className="text-2xl font-bold text-gray-900 mb-6">Similar Products</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              {similarProducts.map((similarProduct) => (
                <ProductCard key={similarProduct.id} product={similarProduct} />
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}

export default ProductDetail