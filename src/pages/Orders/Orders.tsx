import { useState } from 'react'
import { motion } from 'framer-motion'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../contexts/AuthContext'
import LoadingSpinner from '../../components/UI/LoadingSpinner'
import Button from '../../components/UI/Button'

interface Order {
  id: number
  total_price: number
  status: 'pending' | 'confirmed' | 'shipped' | 'delivered' | 'cancelled'
  created_at: string
  seller_name: string
  items: Array<{
    product_name: string
    quantity: number
    price: number
    subtotal: number
  }>
}

const Orders = () => {
  const { user } = useAuth()
  const [statusFilter, setStatusFilter] = useState<string>('all')

  const { data: orders = [], isLoading } = useQuery({
    queryKey: ['orders'],
    queryFn: async () => {
      // Mock data - replace with actual API call
      return [
        {
          id: 1,
          total_price: 15.98,
          status: 'pending',
          created_at: '2024-01-15',
          seller_name: 'Farm Fresh Co.',
          items: [
            {
              product_name: 'Fresh Tomatoes',
              quantity: 2,
              price: 5.99,
              subtotal: 11.98,
            }
          ]
        },
        {
          id: 2,
          total_price: 23.47,
          status: 'delivered',
          created_at: '2024-01-10',
          seller_name: 'Green Valley Farm',
          items: [
            {
              product_name: 'Organic Carrots',
              quantity: 3,
              price: 3.49,
              subtotal: 10.47,
            },
            {
              product_name: 'Fresh Spinach',
              quantity: 2,
              price: 2.99,
              subtotal: 5.98,
            }
          ]
        }
      ] as Order[]
    }
  })

  const statusCounts = {
    all: orders.length,
    pending: orders.filter(o => o.status === 'pending').length,
    confirmed: orders.filter(o => o.status === 'confirmed').length,
    shipped: orders.filter(o => o.status === 'shipped').length,
    delivered: orders.filter(o => o.status === 'delivered').length,
    cancelled: orders.filter(o => o.status === 'cancelled').length,
  }

  const filteredOrders = statusFilter === 'all' 
    ? orders 
    : orders.filter(order => order.status === statusFilter)

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending': return 'bg-yellow-100 text-yellow-800'
      case 'confirmed': return 'bg-blue-100 text-blue-800'
      case 'shipped': return 'bg-purple-100 text-purple-800'
      case 'delivered': return 'bg-green-100 text-green-800'
      case 'cancelled': return 'bg-red-100 text-red-800'
      default: return 'bg-gray-100 text-gray-800'
    }
  }

  const getProgressWidth = (status: string) => {
    switch (status) {
      case 'pending': return '25%'
      case 'confirmed': return '50%'
      case 'shipped': return '75%'
      case 'delivered': return '100%'
      default: return '0%'
    }
  }

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
          <h1 className="text-3xl font-bold text-gray-900 mb-2">My Orders</h1>
          <p className="text-gray-600">Track and manage your purchases</p>
        </div>

        {/* Status Filter */}
        <div className="mb-8">
          <div className="flex flex-wrap gap-2">
            {Object.entries(statusCounts).map(([status, count]) => (
              <button
                key={status}
                onClick={() => setStatusFilter(status)}
                className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                  statusFilter === status
                    ? 'bg-farm-green text-white'
                    : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'
                }`}
              >
                {status.charAt(0).toUpperCase() + status.slice(1)} ({count})
              </button>
            ))}
          </div>
        </div>

        {/* Orders List */}
        {filteredOrders.length === 0 ? (
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <div className="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
              <svg className="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <h3 className="text-xl font-semibold text-gray-900 mb-2">No orders found</h3>
            <p className="text-gray-600 mb-6">
              {statusFilter === 'all' 
                ? "You haven't placed any orders yet. Start shopping to see your orders here."
                : `No ${statusFilter} orders found.`
              }
            </p>
            <Button onClick={() => window.location.href = '/products'}>
              Start Shopping
            </Button>
          </div>
        ) : (
          <div className="space-y-6">
            {filteredOrders.map((order, index) => (
              <motion.div
                key={order.id}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: index * 0.1 }}
                className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
              >
                {/* Order Header */}
                <div className="px-6 py-4 bg-gray-50 border-b border-gray-200">
                  <div className="flex justify-between items-start">
                    <div>
                      <h3 className="text-lg font-semibold text-gray-900">
                        Order #{order.id}
                      </h3>
                      <p className="text-sm text-gray-600">
                        Placed on {new Date(order.created_at).toLocaleDateString('en-US', {
                          year: 'numeric',
                          month: 'long',
                          day: 'numeric'
                        })}
                      </p>
                      <p className="text-sm text-gray-600">
                        From {order.seller_name}
                      </p>
                    </div>
                    <div className="text-right">
                      <span className={`inline-flex px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(order.status)}`}>
                        {order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                      </span>
                      <p className="text-lg font-bold text-gray-900 mt-1">
                        ${order.total_price.toFixed(2)}
                      </p>
                    </div>
                  </div>
                </div>

                {/* Progress Bar */}
                {order.status !== 'cancelled' && (
                  <div className="px-6 py-4 bg-gray-50">
                    <div className="flex justify-between text-xs text-gray-600 mb-2">
                      <span>Ordered</span>
                      <span>Confirmed</span>
                      <span>Shipped</span>
                      <span>Delivered</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        className="bg-farm-green h-2 rounded-full transition-all duration-300"
                        style={{ width: getProgressWidth(order.status) }}
                      />
                    </div>
                  </div>
                )}

                {/* Order Items */}
                <div className="px-6 py-4">
                  <h4 className="font-medium text-gray-900 mb-3">
                    Order Items ({order.items.length})
                  </h4>
                  <div className="space-y-3">
                    {order.items.map((item, itemIndex) => (
                      <div key={itemIndex} className="flex justify-between items-center">
                        <div>
                          <p className="font-medium text-gray-900">{item.product_name}</p>
                          <p className="text-sm text-gray-600">
                            ${item.price.toFixed(2)} × {item.quantity}
                          </p>
                        </div>
                        <p className="font-medium text-gray-900">
                          ${item.subtotal.toFixed(2)}
                        </p>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Order Actions */}
                <div className="px-6 py-4 bg-gray-50 border-t border-gray-200">
                  <div className="flex justify-between items-center">
                    <div className="flex space-x-3">
                      <Button variant="secondary" size="sm">
                        Contact Seller
                      </Button>
                      {order.status === 'shipped' && (
                        <Button size="sm">
                          Confirm Delivery
                        </Button>
                      )}
                      {(order.status === 'pending' || order.status === 'confirmed') && (
                        <Button variant="danger" size="sm">
                          Cancel Order
                        </Button>
                      )}
                    </div>
                    {order.status === 'delivered' && (
                      <Button variant="secondary" size="sm">
                        Order Again
                      </Button>
                    )}
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        )}

        {/* Order Tips */}
        <div className="mt-12 bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="font-semibold text-blue-900 mb-3">Order Status Guide</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-800">
            <div>
              <strong>Pending:</strong> Your order has been placed and is awaiting confirmation from the seller.
            </div>
            <div>
              <strong>Confirmed:</strong> The seller has confirmed your order and is preparing it for shipping.
            </div>
            <div>
              <strong>Shipped:</strong> Your order is on the way! You'll be able to confirm delivery when it arrives.
            </div>
            <div>
              <strong>Delivered:</strong> Your order has been delivered successfully.
            </div>
          </div>
          <p className="mt-4 text-sm text-blue-700">
            Need help with your order? <a href="mailto:support@farm2door.com" className="underline">Contact our support team</a>.
          </p>
        </div>
      </div>
    </div>
  )
}

export default Orders