// Designer Portal JavaScript
const API_URL = '/api';
let currentDesigner = null;
let currentOrders = [];
let currentFilter = 'all';

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Check if already logged in
    const savedDesigner = localStorage.getItem('designer');
    if (savedDesigner) {
        currentDesigner = JSON.parse(savedDesigner);
        showDashboard();
    }

    // Login form
    document.getElementById('login-form').addEventListener('submit', handleLogin);

    // Logout button
    document.getElementById('logout-btn').addEventListener('click', handleLogout);

    // Refresh button
    document.getElementById('refresh-btn').addEventListener('submit', loadOrders);

    // Status tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const status = e.target.dataset.status;
            filterOrders(status);
        });
    });

    // Modal close
    document.querySelector('.modal-close').addEventListener('click', closeUploadModal);
    document.querySelector('.modal-cancel').addEventListener('click', closeUploadModal);

    // Upload form
    document.getElementById('upload-form').addEventListener('submit', handleUploadDesign);
});

// Handle login
async function handleLogin(e) {
    e.preventDefault();

    const email = document.getElementById('designer-email').value;
    const designerCode = document.getElementById('designer-code').value;
    const errorEl = document.getElementById('login-error');

    try {
        errorEl.textContent = '';

        // Call backend API
        const response = await fetch(`${API_URL}/designer/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, designerCode })
        });

        const data = await response.json();

        if (data.success && data.user) {
            currentDesigner = data.user;
            localStorage.setItem('designer', JSON.stringify(currentDesigner));
            showDashboard();
        } else {
            errorEl.textContent = data.message || 'Invalid credentials';
        }
    } catch (error) {
        console.error('Login error:', error);
        errorEl.textContent = 'Login failed. Please try again.';
    }
}

// Handle logout
function handleLogout() {
    currentDesigner = null;
    localStorage.removeItem('designer');
    showLogin();
}

// Show login screen
function showLogin() {
    document.getElementById('login-screen').classList.add('active');
    document.getElementById('dashboard-screen').classList.remove('active');
}

// Show dashboard
function showDashboard() {
    document.getElementById('login-screen').classList.remove('active');
    document.getElementById('dashboard-screen').classList.add('active');
    document.getElementById('designer-name').textContent = currentDesigner.name;
    loadOrders();
}

// Load designer orders
async function loadOrders() {
    const loadingEl = document.getElementById('orders-loading');
    const listEl = document.getElementById('orders-list');
    const noOrdersEl = document.getElementById('no-orders');

    try {
        loadingEl.style.display = 'flex';
        listEl.innerHTML = '';
        noOrdersEl.style.display = 'none';

        const response = await fetch(`${API_URL}/designer/orders`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ designerCode: currentDesigner.designerCode })
        });

        const data = await response.json();

        if (data.success && data.orders) {
            currentOrders = data.orders;
            filterOrders(currentFilter);
        } else {
            noOrdersEl.style.display = 'block';
        }
    } catch (error) {
        console.error('Load orders error:', error);
        alert('Failed to load orders');
    } finally {
        loadingEl.style.display = 'none';
    }
}

// Filter orders by status
function filterOrders(status) {
    currentFilter = status;

    // Update active tab
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.status === status);
    });

    // Filter orders
    const filtered = status === 'all'
        ? currentOrders
        : currentOrders.filter(order => order.Status === status);

    renderOrders(filtered);
}

// Render orders
function renderOrders(orders) {
    const listEl = document.getElementById('orders-list');
    const noOrdersEl = document.getElementById('no-orders');

    if (orders.length === 0) {
        listEl.innerHTML = '';
        noOrdersEl.style.display = 'block';
        return;
    }

    noOrdersEl.style.display = 'none';

    listEl.innerHTML = orders.map(order => `
        <div class="order-card">
            <div class="order-header">
                <div class="order-id">
                    <strong>Order #${order['Order ID'] || order.orderID}</strong>
                    <span class="status-badge status-${order.Status}">${order.Status}</span>
                </div>
                <div class="order-date">${formatDate(order.Date)}</div>
            </div>
            <div class="order-body">
                <div class="order-product">
                    ${order.MOCKUPURL ? `<img src="${order.MOCKUPURL}" class="product-thumb" alt="Product">` : ''}
                    <div class="product-info">
                        <div><strong>${order.ProductName || 'N/A'}</strong></div>
                        <div>Size: ${order.Size} | Color: ${order.Color}</div>
                        <div>Type: ${order['Product Type'] || 'N/A'}</div>
                    </div>
                </div>
                <div class="order-customer">
                    <div><strong>Customer:</strong> ${order.FirstName} ${order.LastName}</div>
                    <div><strong>Platform:</strong> ${order.Platform}</div>
                </div>
            </div>
            <div class="order-actions">
                ${order.Status === 'DESIGNING' || order.Status === 'PENDING' ? `
                    <button class="btn-primary" onclick="openUploadModal('${order['Order ID'] || order.orderID}')">
                        📤 Upload Design
                    </button>
                ` : `
                    <button class="btn-secondary" onclick="viewDesign('${order['Order ID'] || order.orderID}')">
                        👁️ View Design
                    </button>
                `}
            </div>
        </div>
    `).join('');
}

// Open upload modal
function openUploadModal(orderId) {
    const order = currentOrders.find(o => String(o['Order ID'] || o.orderID) === String(orderId));
    if (!order) return;

    document.getElementById('modal-order-id').textContent = orderId;
    document.getElementById('modal-product-name').textContent = order.ProductName || 'N/A';
    document.getElementById('modal-size').textContent = order.Size || 'N/A';
    document.getElementById('modal-color').textContent = order.Color || 'N/A';

    document.getElementById('upload-modal').classList.add('active');
    document.getElementById('upload-form').dataset.orderId = orderId;
}

// Close upload modal
function closeUploadModal() {
    document.getElementById('upload-modal').classList.remove('active');
    document.getElementById('upload-form').reset();
}

// Handle upload design
async function handleUploadDesign(e) {
    e.preventDefault();

    const orderId = e.target.dataset.orderId;
    const designData = {
        orderId: orderId,
        designerCode: currentDesigner.designerCode,
        printLocation: document.getElementById('print-location').value,
        designUrl: document.getElementById('design-url').value,
        mockupUrl: document.getElementById('mockup-url').value,
        printLocation2: document.getElementById('print-location-2').value,
        designUrl2: document.getElementById('design-url-2').value,
        mockupUrl2: document.getElementById('mockup-url-2').value
    };

    try {
        const response = await fetch(`${API_URL}/designer/upload`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(designData)
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ Design uploaded successfully!');
            closeUploadModal();
            loadOrders(); // Reload orders
        } else {
            alert('❌ Upload failed: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Upload error:', error);
        alert('❌ Upload failed. Please try again.');
    }
}

// View design
function viewDesign(orderId) {
    const order = currentOrders.find(o => String(o['Order ID'] || o.orderID) === String(orderId));
    if (!order) return;

    const designUrl = order.DESIGNURL;
    const mockupUrl = order.MOCKUPURL;

    if (designUrl) {
        window.open(designUrl, '_blank');
    } else {
        alert('No design uploaded yet');
    }
}

// Format date
function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    try {
        return new Date(dateStr).toLocaleDateString();
    } catch {
        return dateStr;
    }
}
