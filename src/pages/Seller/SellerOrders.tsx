import { useState } from 'react'
import { motion } from 'framer-motion'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../contexts/AuthContext'
import LoadingSpinner from '../../components/UI/LoadingSpinner'
import Button from '../../components/UI/Button'
import toast from 'react-hot-toast'

interface SellerOrder {
  id: number
  buyer_id: number
  total_price: number
  status: 'pending' | 'confirmed' | 'shipped' | 'delivered' | 'cancelled'
  delivery_address: string
  phone_number: string
  notes?: string
  created_at: string
  buyer_name: string
  buyer_email: string
  items: Array<{
    product_name: string
    quantity: number
    price: number
    subtotal: number
  }>
}

const SellerOrders = () => {
  const { user } = useAuth()
  const [statusFilter, setStatusFilter] = useState<string>('all')

  const { data: orders = [], isLoading } = useQuery({
    queryKey: ['seller-orders'],
    queryFn: async () => {
      // Mock data - replace with actual API call
      return [
        {
          id: 1,
          buyer_id: 2,
          total_price: 120.00,
          status: 'pending',
          delivery_address: '123 Main St, City, State 12345',
          phone_number: '(555) 123-4567',
          notes: 'Please deliver to the back door',
          created_at: '2024-01-15',
          buyer_name: 'John Doe',
          buyer_email: 'john@example.com',
          items: [
            {
              product_name: 'Fresh Tomatoes',
              quantity: 10,
              price: 5.99,
              subtotal: 59.90,
            },
            {
              product_name: 'Organic Carrots',
              quantity: 5,
              price: 3.49,
              subtotal: 17.45,
            }
          ]
        },
        {
          id: 2,
          buyer_id: 3,
          total_price: 45.50,
          status: 'confirmed',
          delivery_address: '456 Oak Ave, City, State 12345',
          phone_number: '(555) 987-6543',
          created_at: '2024-01-14',
          buyer_name: 'Jane Smith',
          buyer_email: 'jane@example.com',
          items: [
            {
              product_name: 'Fresh Spinach',
              quantity: 8,
              price: 2.99,
              subtotal: 23.92,
            }
          ]
        }
      ] as SellerOrder[]
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

  const handleOrderAction = async (orderId: number, action: string) => {
    try {
      // Mock API call - replace with actual implementation
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      switch (action) {
        case 'confirm':
          toast.success(`Order #${orderId} confirmed successfully!`)
          break
        case 'ship':
          toast.success(`Order #${orderId} marked as shipped!`)
          break
        case 'deliver':
          toast.success(`Order #${orderId} marked as delivered!`)
          break
        case 'cancel':
          if (confirm('Are you sure you want to cancel this order?')) {
            toast.success(`Order #${orderId} cancelled successfully!`)
          }
          break
      }
    } catch (error) {
      toast.error('Failed to update order status')
    }
  }

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
          <h1 className="text-3xl font-bold text-gray-900 mb-2">My Shop & Incoming Orders</h1>
          <p className="text-gray-600">Manage your shop orders and track customer purchases</p>
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
                ? "You don't have any orders yet. When customers place orders, they will appear here."
                : `No ${statusFilter} orders found.`
              }
            </p>
            <Button onClick={() => window.location.href = '/dashboard'}>
              Manage Your Products
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
                        From {order.buyer_name}
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

                {/* Order Content */}
                <div className="p-6">
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Order Items */}
                    <div>
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

                    {/* Customer & Delivery Info */}
                    <div>
                      <h4 className="font-medium text-gray-900 mb-3">Customer Information</h4>
                      <div className="space-y-2 text-sm">
                        <p><strong>Name:</strong> {order.buyer_name}</p>
                        <p><strong>Email:</strong> {order.buyer_email}</p>
                        <p><strong>Phone:</strong> {order.phone_number}</p>
                        <p><strong>Delivery Address:</strong></p>
                        <p className="text-gray-600 ml-4">{order.delivery_address}</p>
                        {order.notes && (
                          <>
                            <p><strong>Notes:</strong></p>
                            <p className="text-gray-600 ml-4">{order.notes}</p>
                          </>
                        )}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Order Actions */}
                <div className="px-6 py-4 bg-gray-50 border-t border-gray-200">
                  <div className="flex justify-between items-center">
                    <div className="flex space-x-3">
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => window.open(`mailto:${order.buyer_email}`)}
                      >
                        Contact Buyer
                      </Button>
                    </div>
                    
                    <div className="flex space-x-3">
                      {order.status === 'pending' && (
                        <>
                          <Button
                            size="sm"
                            onClick={() => handleOrderAction(order.id, 'confirm')}
                          >
                            Confirm Order
                          </Button>
                          <Button
                            variant="danger"
                            size="sm"
                            onClick={() => handleOrderAction(order.id, 'cancel')}
                          >
                            Reject Order
                          </Button>
                        </>
                      )}
                      
                      {order.status === 'confirmed' && (
                        <>
                          <Button
                            size="sm"
                            onClick={() => handleOrderAction(order.id, 'ship')}
                          >
                            Mark as Shipped
                          </Button>
                          <Button
                            variant="danger"
                            size="sm"
                            onClick={() => handleOrderAction(order.id, 'cancel')}
                          >
                            Cancel Order
                          </Button>
                        </>
                      )}
                      
                      {order.status === 'shipped' && (
                        <Button
                          size="sm"
                          onClick={() => handleOrderAction(order.id, 'deliver')}
                        >
                          Mark as Delivered
                        </Button>
                      )}
                      
                      {order.status === 'delivered' && (
                        <span className="text-sm text-green-600 font-medium">
                          Order Completed ✓
                        </span>
                      )}
                      
                      {order.status === 'cancelled' && (
                        <span className="text-sm text-red-600 font-medium">
                          Order Cancelled
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        )}

        {/* Order Management Tips */}
        <div className="mt-12 bg-blue-50 border border-blue-200 rounded-lg p-6">
          <h3 className="font-semibold text-blue-900 mb-3">Order Management Tips</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-800">
            <div>
              <strong>Respond Quickly:</strong> Confirm orders within 24 hours to maintain customer satisfaction.
            </div>
            <div>
              <strong>Communicate:</strong> Keep buyers informed about order status and any delays.
            </div>
            <div>
              <strong>Quality Control:</strong> Ensure products meet the quality standards described in your listings.
            </div>
            <div>
              <strong>Packaging:</strong> Use proper packaging to ensure products arrive fresh and undamaged.
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default SellerOrders