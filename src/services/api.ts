import axios from 'axios'

// Configure axios defaults
const API_BASE_URL = 'http://localhost' // Adjust to your PHP backend URL

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
})

// Add auth token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handle response errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      window.location.href = '/signin'
    }
    return Promise.reject(error)
  }
)

// Auth API
export const authAPI = {
  login: async (email: string, password: string) => {
    const formData = new FormData()
    formData.append('email', email)
    formData.append('password', password)
    
    const response = await fetch(`${API_BASE_URL}/sign-in.php`, {
      method: 'POST',
      body: formData,
    })
    
    if (response.ok) {
      // Simulate successful login response
      return {
        success: true,
        token: 'mock-token',
        user: {
          id: 1,
          first_name: 'John',
          last_name: 'Doe',
          email: email,
          user_type: 'buyer',
        }
      }
    }
    
    throw new Error('Invalid credentials')
  },

  register: async (userData: any) => {
    const formData = new FormData()
    Object.keys(userData).forEach(key => {
      formData.append(key, userData[key])
    })
    
    const response = await fetch(`${API_BASE_URL}/config_files/process_signup.php`, {
      method: 'POST',
      body: formData,
    })
    
    if (response.ok) {
      return {
        success: true,
        token: 'mock-token',
        user: {
          id: 2,
          first_name: userData.firstName,
          last_name: userData.lastName,
          email: userData.email,
          user_type: userData.userType,
        }
      }
    }
    
    throw new Error('Registration failed')
  },

  getProfile: async () => {
    // Mock profile data
    return {
      id: 1,
      first_name: 'John',
      last_name: 'Doe',
      email: 'john@example.com',
      user_type: 'buyer',
    }
  },
}

// Products API
export const productsAPI = {
  getProducts: async (params?: any) => {
    const queryParams = new URLSearchParams(params).toString()
    const response = await fetch(`${API_BASE_URL}/home.php?${queryParams}`)
    
    // Mock products data
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
    ]
  },

  getProduct: async (id: number) => {
    // Mock single product data
    return {
      id: id,
      name: 'Fresh Tomatoes',
      description: 'Organic red tomatoes, perfect for salads and cooking. Grown without pesticides in our sustainable farm.',
      price: 5.99,
      quantity: 50,
      category: 'Vegetables',
      image: 'https://images.unsplash.com/photo-1546470427-e26264be0b0d?w=800',
      user_id: 2,
      seller: {
        id: 2,
        first_name: 'Jane',
        last_name: 'Smith',
        email: 'jane@farmfresh.com',
        location: 'California',
        farm_name: 'Farm Fresh Co.',
      },
      created_at: '2024-01-15',
    }
  },

  getCategories: async () => {
    return [
      { category: 'Vegetables', count: 15 },
      { category: 'Fruits', count: 12 },
      { category: 'Meat', count: 8 },
      { category: 'Dairy', count: 6 },
      { category: 'Grains', count: 10 },
      { category: 'Fish', count: 5 },
    ]
  },
}

// Cart API
export const cartAPI = {
  getCart: async () => {
    // Mock cart data
    return [
      {
        id: 1,
        product_id: 1,
        quantity: 2,
        product: {
          id: 1,
          name: 'Fresh Tomatoes',
          price: 5.99,
          image: 'https://images.unsplash.com/photo-1546470427-e26264be0b0d?w=400',
          user_id: 2,
          seller_name: 'Farm Fresh Co.',
        }
      }
    ]
  },

  addToCart: async (productId: number, quantity: number) => {
    const formData = new FormData()
    formData.append('action', 'add_to_cart')
    formData.append('product_id', productId.toString())
    formData.append('quantity', quantity.toString())
    
    const response = await fetch(`${API_BASE_URL}/home.php`, {
      method: 'POST',
      body: formData,
    })
    
    if (!response.ok) {
      throw new Error('Failed to add to cart')
    }
  },

  updateQuantity: async (cartId: number, quantity: number) => {
    const formData = new FormData()
    formData.append('action', 'update_quantity')
    formData.append('cart_id', cartId.toString())
    formData.append('quantity', quantity.toString())
    
    const response = await fetch(`${API_BASE_URL}/user-checkout-cart.php`, {
      method: 'POST',
      body: formData,
    })
    
    if (!response.ok) {
      throw new Error('Failed to update quantity')
    }
  },

  removeItem: async (cartId: number) => {
    const formData = new FormData()
    formData.append('action', 'remove_item')
    formData.append('cart_id', cartId.toString())
    
    const response = await fetch(`${API_BASE_URL}/user-checkout-cart.php`, {
      method: 'POST',
      body: formData,
    })
    
    if (!response.ok) {
      throw new Error('Failed to remove item')
    }
  },

  clearCart: async () => {
    const formData = new FormData()
    formData.append('action', 'clear_cart')
    
    const response = await fetch(`${API_BASE_URL}/user-checkout-cart.php`, {
      method: 'POST',
      body: formData,
    })
    
    if (!response.ok) {
      throw new Error('Failed to clear cart')
    }
  },
}

// Orders API
export const ordersAPI = {
  getOrders: async () => {
    // Mock orders data
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
      }
    ]
  },

  createOrder: async (orderData: any) => {
    const formData = new FormData()
    Object.keys(orderData).forEach(key => {
      formData.append(key, orderData[key])
    })
    
    const response = await fetch(`${API_BASE_URL}/user-checkout-cart.php`, {
      method: 'POST',
      body: formData,
    })
    
    if (!response.ok) {
      throw new Error('Failed to create order')
    }
    
    return { success: true, order_id: 123 }
  },
}

// Favorites API
export const favoritesAPI = {
  getFavorites: async () => {
    // Mock favorites data
    return [
      {
        id: 1,
        product_id: 2,
        product: {
          id: 2,
          name: 'Organic Carrots',
          price: 3.49,
          image: 'https://images.unsplash.com/photo-1445282768818-728615cc910a?w=400',
          user_id: 3,
          seller_name: 'Green Valley Farm',
          quantity: 30,
        }
      }
    ]
  },

  addToFavorites: async (productId: number) => {
    const formData = new FormData()
    formData.append('action', 'add_to_favorites')
    formData.append('product_id', productId.toString())
    
    const response = await fetch(`${API_BASE_URL}/home.php`, {
      method: 'POST',
      body: formData,
    })
    
    if (!response.ok) {
      throw new Error('Failed to add to favorites')
    }
  },

  removeFromFavorites: async (productId: number) => {
    const formData = new FormData()
    formData.append('action', 'remove_favorite')
    formData.append('product_id', productId.toString())
    
    const response = await fetch(`${API_BASE_URL}/favorites.php`, {
      method: 'POST',
      body: formData,
    })
    
    if (!response.ok) {
      throw new Error('Failed to remove from favorites')
    }
  },
}

export default api