#!/bin/bash
# Setup script for Pixel Scout test environment
# Uses @wordpress/env (wp-env) for WordPress testing environment

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║   Pixel Scout Test Environment Setup                           ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Helper functions
print_step() {
    echo -e "${BLUE}[*] $1${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Check for required tools
check_requirements() {
    local missing=0

    # Check Node.js
    if ! command -v node &> /dev/null; then
        print_error "Node.js is required but not installed"
        missing=1
    else
        print_success "Node.js found: $(node -v)"
    fi

    # Check npm
    if ! command -v npm &> /dev/null; then
        print_error "npm is required but not installed"
        missing=1
    else
        print_success "npm found: $(npm -v)"
    fi

    # Check PHP
    if ! command -v php &> /dev/null; then
        print_error "PHP is required but not installed"
        missing=1
    else
        print_success "PHP found: $(php -v | head -1)"
    fi

    # Check Docker
    if ! command -v docker &> /dev/null; then
        print_warning "Docker is recommended for wp-env"
        print_warning "Install from: https://docs.docker.com/get-docker/"
    else
        print_success "Docker found: $(docker --version)"
    fi

    if [ $missing -eq 1 ]; then
        return 1
    fi
    return 0
}

# Install wp-env globally if not present
install_wp_env() {
    print_step "Checking @wordpress/env..."

    if npm list -g @wordpress/env > /dev/null 2>&1; then
        print_success "@wordpress/env already installed globally"
    else
        print_step "Installing @wordpress/env globally..."
        npm install -g @wordpress/env
        print_success "@wordpress/env installed"
    fi
}

# Start WordPress environment
start_wp_env() {
    print_step "Starting WordPress test environment..."
    print_warning "This may take 1-2 minutes on first run"

    wp-env start

    print_success "WordPress environment started"
    print_warning "Waiting for WordPress to be fully ready..."
    sleep 5

    wp-env run cli wp core is-installed
    if [ $? -eq 0 ]; then
        print_success "WordPress is ready!"
    else
        print_step "Initializing WordPress..."
        wp-env run cli wp core install \
            --url=http://localhost:8000 \
            --title="Pixel Scout Test" \
            --admin_user=wordpress \
            --admin_password=wordpress \
            --admin_email=test@example.com
        print_success "WordPress initialized"
    fi
}

# Activate the plugin
activate_plugin() {
    print_step "Activating Pixel Scout plugin..."
    wp-env run cli wp plugin activate pixel-scout
    print_success "Plugin activated"
}

# Main setup flow
main() {
    echo ""

    # 1. Check requirements
    echo -e "${BLUE}[1/6] Checking requirements...${NC}"
    if ! check_requirements; then
        print_error "Missing required tools. Please install them first."
        exit 1
    fi
    echo ""

    # 2. Install wp-env
    echo -e "${BLUE}[2/6] Setting up @wordpress/env...${NC}"
    install_wp_env
    echo ""

    # 3. Install PHP dependencies
    echo -e "${BLUE}[3/6] Installing PHP dependencies...${NC}"
    composer install --dev
    print_success "PHP dependencies installed"
    echo ""

    # 4. Install Node dependencies
    echo -e "${BLUE}[4/6] Installing Node dependencies...${NC}"
    npm install
    print_success "Node dependencies installed"
    echo ""

    # 5. Start WordPress environment
    echo -e "${BLUE}[5/6] Starting WordPress environment...${NC}"
    start_wp_env
    echo ""

    # 6. Setup environment file
    echo -e "${BLUE}[6/6] Setting up environment files...${NC}"
    if [ ! -f .env ]; then
        cp .env.example .env
        print_success "Created .env file"
        print_warning "Review .env and update if needed"
    else
        print_success ".env file already exists"
    fi
    echo ""

    # Final instructions
    echo -e "${GREEN}════════════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}✓ Setup Complete!${NC}"
    echo -e "${GREEN}════════════════════════════════════════════════════════════════${NC}"
    echo ""

    echo "WordPress is running at: ${BLUE}http://localhost:8000${NC}"
    echo "Admin URL: ${BLUE}http://localhost:8000/wp-admin${NC}"
    echo "Username: ${BLUE}wordpress${NC}"
    echo "Password: ${BLUE}wordpress${NC}"
    echo ""

    echo -e "${BLUE}UNIT TESTS (PHPUnit):${NC}"
    echo "  💻 composer test              # Run all tests"
    echo "  📊 composer test-coverage     # With coverage report"
    echo "  ✨ composer lint              # Code standards"
    echo "  🔧 composer lint-fix          # Auto-fix standards"
    echo ""

    echo -e "${BLUE}E2E TESTS (Playwright):${NC}"
    echo "  🎭 npm run test:e2e           # Run all E2E tests"
    echo "  🎨 npm run test:e2e:ui        # Interactive UI mode"
    echo "  👀 npm run test:e2e:headed    # Visible browser"
    echo "  🐛 npm run test:e2e:debug     # Debug mode"
    echo ""

    echo -e "${BLUE}WordPress Environment (wp-env):${NC}"
    echo "  🏠 wp-env start               # Start environment"
    echo "  🛑 wp-env stop                # Stop environment"
    echo "  🔄 wp-env destroy             # Remove everything (reset)"
    echo "  📋 wp-env run cli wp ...      # Run WP-CLI commands"
    echo "  💻 wp-env run phpunit         # Run PHPUnit in wp-env"
    echo ""

    echo -e "${BLUE}Documentation:${NC}"
    echo "  📖 See TESTING.md for complete testing guide"
    echo "  📋 See SETUP_WORDPRESS.md for wp-env details"
    echo "  🔗 https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/"
    echo ""

    echo "Ready to test! 🚀"
    echo ""
}

# Run main setup
main



