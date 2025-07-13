import { Link } from 'react-router-dom'

const Footer = () => {
  const footerLinks = [
    { name: 'Home', href: '/' },
    { name: 'Products', href: '/products' },
    { name: 'Dashboard', href: '/dashboard' },
    { name: 'Orders', href: '/orders' },
    { name: 'Cart', href: '/cart' },
    { name: 'Settings', href: '/settings' },
  ]

  return (
    <footer className="bg-white border-t border-gray-200 mt-auto">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          {/* Links */}
          <div>
            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">
              Quick Links
            </h3>
            <div className="grid grid-cols-2 gap-2">
              {footerLinks.map((link) => (
                <Link
                  key={link.name}
                  to={link.href}
                  className="text-gray-600 hover:text-farm-green transition-colors duration-200"
                >
                  {link.name}
                </Link>
              ))}
            </div>
          </div>

          {/* Brand */}
          <div>
            <div className="flex items-center space-x-2 mb-4">
              <div className="w-8 h-8 bg-gradient-to-br from-farm-green to-green-600 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-sm">F2D</span>
              </div>
              <span className="text-xl font-bold text-gray-900">Farm2Door</span>
            </div>
            <p className="text-gray-600 text-sm leading-relaxed">
              Leveraging innovative e-commerce technology to solve food problems and connect farmers directly with consumers.
            </p>
          </div>
        </div>

        <div className="mt-8 pt-8 border-t border-gray-200">
          <p className="text-center text-gray-500 text-sm">
            © 2024 Farm2Door. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  )
}

export default Footer