import { useState, useEffect } from 'react'
import { motion } from 'framer-motion'
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/react/24/outline'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../../contexts/AuthContext'
import Button from '../../components/UI/Button'
import Input from '../../components/UI/Input'
import LoadingSpinner from '../../components/UI/LoadingSpinner'
import toast from 'react-hot-toast'

interface Product {
  id: number
  name: string
  description: string
  price: number
  quantity: number
  category: string
  image?: string
  created_at: string
}

interface UserStats {
  total_products: number
  total_sales: number
  total_purchases: number
  total_earnings: number
}

const Dashboard = () => {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const [isAddModalOpen, setIsAddModalOpen] = useState(false)
  const [editingProduct, setEditingProduct] = useState<Product | null>(null)
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    price: '',
    quantity: '',
    category: 'Vegetables'
  })

  // Fetch user products
  const { data: products = [], isLoading: productsLoading } = useQuery({
    queryKey: ['user-products'],
    queryFn: async () => {
      // Mock data - replace with actual API call
      return [
        {
          id: 1,
          name: 'Fresh Tomatoes',
          description: 'Organic red tomatoes',
          price: 5.99,
          quantity: 50,
          category: 'Vegetables',
          created_at: '2024-01-15'
        },
        {
          id: 2,
          name: 'Sweet Corn',
          description: 'Fresh sweet corn',
          price: 3.49,
          quantity: 30,
          category: 'Vegetables',
          created_at: '2024-01-14'
        }
      ] as Product[]
    }
  })

  // Fetch user stats
  const { data: stats } = useQuery({
    queryKey: ['user-stats'],
    queryFn: async () => {
      // Mock data - replace with actual API call
      return {
        total_products: products.length,
        total_sales: 15,
        total_purchases: 8,
        total_earnings: 450.00
      } as UserStats
    }
  })

  // Add product mutation
  const addProductMutation = useMutation({
    mutationFn: async (productData: any) => {
      // Mock API call - replace with actual implementation
      const formData = new FormData()
      formData.append('action', 'add_product')
      formData.append('product_name', productData.name)
      formData.append('description', productData.description)
      formData.append('price', productData.price)
      formData.append('quantity', productData.quantity)
      formData.append('category', productData.category)

      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 1000))
      return { success: true }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-products'] })
      toast.success('Product added successfully!')
      setIsAddModalOpen(false)
      resetForm()
    },
    onError: () => {
      toast.error('Failed to add product')
    }
  })

  // Update product mutation
  const updateProductMutation = useMutation({
    mutationFn: async ({ id, ...productData }: any) => {
      // Mock API call - replace with actual implementation
      const formData = new FormData()
      formData.append('action', 'update_product')
      formData.append('product_id', id.toString())
      formData.append('product_name', productData.name)
      formData.append('description', productData.description)
      formData.append('price', productData.price)
      formData.append('quantity', productData.quantity)

      await new Promise(resolve => setTimeout(resolve, 1000))
      return { success: true }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-products'] })
      toast.success('Product updated successfully!')
      setEditingProduct(null)
      resetForm()
    },
    onError: () => {
      toast.error('Failed to update product')
    }
  })

  // Delete product mutation
  const deleteProductMutation = useMutation({
    mutationFn: async (productId: number) => {
      // Mock API call - replace with actual implementation
      const formData = new FormData()
      formData.append('action', 'delete_product')
      formData.append('product_id', productId.toString())

      await new Promise(resolve => setTimeout(resolve, 1000))
      return { success: true }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-products'] })
      toast.success('Product deleted successfully!')
    },
    onError: () => {
      toast.error('Failed to delete product')
    }
  })

  const resetForm = () => {
    setFormData({
      name: '',
      description: '',
      price: '',
      quantity: '',
      category: 'Vegetables'
    })
  }

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    
    if (editingProduct) {
      updateProductMutation.mutate({
        id: editingProduct.id,
        ...formData,
        price: parseFloat(formData.price),
        quantity: parseInt(formData.quantity)
      })
    } else {
      addProductMutation.mutate({
        ...formData,
        price: parseFloat(formData.price),
        quantity: parseInt(formData.quantity)
      })
    }
  }

  const handleEdit = (product: Product) => {
    setEditingProduct(product)
    setFormData({
      name: product.name,
      description: product.description,
      price: product.price.toString(),
      quantity: product.quantity.toString(),
      category: product.category
    })
  }

  const handleDelete = (productId: number) => {
    if (confirm('Are you sure you want to delete this product?')) {
      deleteProductMutation.mutate(productId)
    }
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Dashboard</h1>
          <p className="text-gray-600">Welcome back, {user?.first_name}!</p>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
          >
            <h3 className="text-sm font-medium text-gray-500 mb-2">Products Listed</h3>
            <p className="text-3xl font-bold text-gray-900">{stats?.total_products || 0}</p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
          >
            <h3 className="text-sm font-medium text-gray-500 mb-2">Total Sales</h3>
            <p className="text-3xl font-bold text-gray-900">{stats?.total_sales || 0}</p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            className="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
          >
            <h3 className="text-sm font-medium text-gray-500 mb-2">Total Purchases</h3>
            <p className="text-3xl font-bold text-gray-900">{stats?.total_purchases || 0}</p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
          >
            <h3 className="text-sm font-medium text-gray-500 mb-2">Total Earnings</h3>
            <p className="text-3xl font-bold text-green-600">${stats?.total_earnings?.toFixed(2) || '0.00'}</p>
          </motion.div>
        </div>

        {/* Products Section */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
          <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 className="text-xl font-semibold text-gray-900">
              My Products ({products.length})
            </h2>
            <Button
              onClick={() => setIsAddModalOpen(true)}
              className="flex items-center"
            >
              <PlusIcon className="w-4 h-4 mr-2" />
              Add Product
            </Button>
          </div>

          <div className="p-6">
            {productsLoading ? (
              <div className="flex justify-center py-8">
                <LoadingSpinner size="lg" />
              </div>
            ) : products.length === 0 ? (
              <div className="text-center py-8">
                <p className="text-gray-500 mb-4">You haven't added any products yet.</p>
                <Button onClick={() => setIsAddModalOpen(true)}>
                  Add Your First Product
                </Button>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {products.map((product, index) => (
                  <motion.div
                    key={product.id}
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: index * 0.1 }}
                    className="border border-gray-200 rounded-lg overflow-hidden"
                  >
                    <img
                      src={product.image || 'https://images.unsplash.com/photo-1546470427-e26264be0b0d?w=400'}
                      alt={product.name}
                      className="w-full h-48 object-cover"
                    />
                    <div className="p-4">
                      <h3 className="font-semibold text-gray-900 mb-2">{product.name}</h3>
                      <p className="text-sm text-gray-600 mb-2 line-clamp-2">{product.description}</p>
                      <div className="flex justify-between items-center mb-3">
                        <span className="text-lg font-bold text-green-600">
                          ${product.price.toFixed(2)}
                        </span>
                        <span className="text-sm text-gray-500">
                          Qty: {product.quantity}
                        </span>
                      </div>
                      <div className="flex gap-2">
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => handleEdit(product)}
                          className="flex-1"
                        >
                          <PencilIcon className="w-4 h-4 mr-1" />
                          Edit
                        </Button>
                        <Button
                          variant="danger"
                          size="sm"
                          onClick={() => handleDelete(product.id)}
                          loading={deleteProductMutation.isPending}
                        >
                          <TrashIcon className="w-4 h-4" />
                        </Button>
                      </div>
                    </div>
                  </motion.div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Add/Edit Product Modal */}
        {(isAddModalOpen || editingProduct) && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              className="bg-white rounded-lg shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto"
            >
              <div className="px-6 py-4 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">
                  {editingProduct ? 'Edit Product' : 'Add New Product'}
                </h3>
              </div>

              <form onSubmit={handleSubmit} className="p-6 space-y-4">
                <Input
                  label="Product Name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  required
                />

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Category
                  </label>
                  <select
                    value={formData.category}
                    onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green"
                  >
                    <option value="Vegetables">Vegetables</option>
                    <option value="Fruits">Fruits</option>
                    <option value="Grains">Grains</option>
                    <option value="Dairy">Dairy</option>
                    <option value="Meat">Meat</option>
                    <option value="Fish">Fish</option>
                    <option value="Other">Other</option>
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <Input
                    label="Price ($)"
                    type="number"
                    step="0.01"
                    min="0"
                    value={formData.price}
                    onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                    required
                  />
                  <Input
                    label="Quantity"
                    type="number"
                    min="0"
                    value={formData.quantity}
                    onChange={(e) => setFormData({ ...formData, quantity: e.target.value })}
                    required
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Description
                  </label>
                  <textarea
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    rows={4}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-farm-green focus:border-farm-green resize-vertical"
                  />
                </div>

                <div className="flex gap-3 pt-4">
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => {
                      setIsAddModalOpen(false)
                      setEditingProduct(null)
                      resetForm()
                    }}
                    className="flex-1"
                  >
                    Cancel
                  </Button>
                  <Button
                    type="submit"
                    loading={addProductMutation.isPending || updateProductMutation.isPending}
                    className="flex-1"
                  >
                    {editingProduct ? 'Update Product' : 'Add Product'}
                  </Button>
                </div>
              </form>
            </motion.div>
          </div>
        )}
      </div>
    </div>
  )
}

export default Dashboard