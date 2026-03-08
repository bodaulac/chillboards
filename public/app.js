// API Base URL
const API_BASE = '/api/v1';

// JWT Token Management
function getToken() { return localStorage.getItem('jwt_token'); }
function setToken(token) { localStorage.setItem('jwt_token', token); }
function removeToken() { localStorage.removeItem('jwt_token'); }
function getAuthHeaders() {
    const token = getToken();
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };
    if (token) headers['Authorization'] = `Bearer ${token}`;
    return headers;
}

// Check authentication on page load
window.addEventListener('DOMContentLoaded', () => {
    if (getToken()) {
        showDashboard();
    }
});

function showDashboard() {
    document.getElementById('login-screen').classList.add('opacity-0');
    setTimeout(() => {
        document.getElementById('login-screen').style.display = 'none';
        document.getElementById('dashboard-screen').classList.remove('hidden');
        switchTab('dashboard');
    }, 500);
}

// ===== AUTH =====
document.getElementById('login-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;

    try {
        const response = await fetch(`${API_BASE}/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await response.json();
        if (data.success && data.token) {
            setToken(data.token);
            document.getElementById('user-name-display').textContent = email.split('@')[0];
            showDashboard();
        } else {
            alert('Access Denied: ' + (data.message || 'Check credentials'));
        }
    } catch (error) {
        alert('Connection error. Is API online?');
    }
});

function logout() {
    removeToken();
    location.reload();
}

// ===== NAVIGATION =====
function switchTab(tabName) {
    // UI Updates
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));

    const activeTab = document.getElementById(`${tabName}-tab`);
    if (activeTab) activeTab.classList.add('active');

    const navBtn = document.getElementById(`nav-${tabName}`);
    if (navBtn) navBtn.classList.add('active');

    const titles = {
        'dashboard': 'Dashboard Overview',
        'orders': 'Order Management',
        'fulfillment': 'Fulfillment Center',
        'products': 'Product Catalog',
        'trends': 'Market Trends',
        'sellers': 'Seller Analytics',
        'settings': 'System Configuration',
        'teams': 'Team Management',
        'wiki': 'Knowledge Base',
        'order-detail': 'Order Details'
    };
    document.getElementById('current-tab-title').textContent = titles[tabName] || 'ChillBoard';

    // Data Loading
    switch (tabName) {
        case 'dashboard':
            loadDashboardAnalytics();
            loadNews();
            loadMiniTrends();
            loadRecentOrders();
            break;
        case 'orders':
            loadOrders();
            break;
        case 'fulfillment':
            loadFulfillment();
            break;
        case 'trends':
            loadTrends();
            break;
        case 'sellers':
            loadSellers();
            break;
        case 'products':
            loadProducts();
            initProductGSheetConfig();
            break;
        case 'settings':
            loadStores();
            break;
        case 'teams':
            loadTeams();
            break;
        case 'wiki':
            loadWikiContent('sync-guide');
            break;
    }
}

// ===== WIKI SYSTEM =====

const WIKI_ARTICLES = {
    'general': `
        <h1>🏠 Tổng quan ChillBoard</h1>
        <p class="text-lg">Chào mừng bạn đến với ChillBoard. Đây là nền tảng tập trung để quản lý toàn bộ quy trình từ đơn hàng đến sản xuất và giao hàng.</p>
        
        <h2>📊 Các Module Chính</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 my-4">
            <div class="p-4 bg-slate-800/30 rounded-lg border border-slate-700">
                <h3 class="text-vibrant-primary font-bold mb-2">📈 Dashboard</h3>
                <p class="text-sm">Xem thống kê tổng quan theo thời gian thực: đơn hàng pending, designing, review, production và shipped.</p>
            </div>
            <div class="p-4 bg-slate-800/30 rounded-lg border border-slate-700">
                <h3 class="text-vibrant-primary font-bold mb-2">📦 Orders</h3>
                <p class="text-sm">Quản lý đơn hàng từ lúc đặt đến lúc giao. Theo dõi trạng thái, cập nhật thiết kế, và xử lý fulfillment.</p>
            </div>
            <div class="p-4 bg-slate-800/30 rounded-lg border border-slate-700">
                <h3 class="text-vibrant-primary font-bold mb-2">📦 Products</h3>
                <p class="text-sm">Quản lý catalog sản phẩm, đồng bộ từ Walmart/Shopify, cập nhật thông tin và giá.</p>
            </div>
            <div class="p-4 bg-slate-800/30 rounded-lg border border-slate-700">
                <h3 class="text-vibrant-primary font-bold mb-2">👥 Teams</h3>
                <p class="text-sm">Quản lý đội nhóm bán hàng, phân quyền Store, và đồng bộ design từ Google Sheets.</p>
            </div>
        </div>

        <h2>🔐 Phân quyền Người dùng</h2>
        <ul class="list-disc pl-6 space-y-2 mt-2">
            <li><strong>Admin:</strong> Toàn quyền quản lý hệ thống, tạo Teams, cấu hình Stores.</li>
            <li><strong>Leader:</strong> Quản lý Team của mình, thêm members, phân quyền Stores.</li>
            <li><strong>Seller:</strong> Xem và xử lý đơn hàng được phân quyền.</li>
        </ul>
    `,
    'sync-guide': `
        <h1>🔄 Hướng dẫn Cấu hình Google Sheet cho Team Product Sync</h1>
        <p>Tính năng này cho phép đồng bộ thông tin bổ sung (như Design URL, Mockup URL) vào sản phẩm dựa trên SKU.</p>
        
        <h2>1. Cấu trúc File Google Sheet</h2>
        <p>Bạn cần tạo một Google Sheet với dòng đầu tiên là tiêu đề (Header). Hệ thống sẽ tự động nhận diện các cột dựa trên tên tiêu đề (không phân biệt hoa thường).</p>
        
        <table class="w-full text-left border-collapse my-4">
            <thead>
                <tr class="bg-slate-800/50 text-slate-300">
                    <th class="p-3 border border-slate-700">Dữ liệu</th>
                    <th class="p-3 border border-slate-700">Tên cột chấp nhận</th>
                    <th class="p-3 border border-slate-700">Mô tả</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="p-3 border border-slate-700 text-white font-medium">SKU</td>
                    <td class="p-3 border border-slate-700 font-mono text-xs">sku, product sku, item sku</td>
                    <td class="p-3 border border-slate-700 text-sm">Mã biến thể cụ thể (VD: TS-BLK-L). <strong>Bắt buộc</strong>.</td>
                </tr>
                <tr>
                    <td class="p-3 border border-slate-700 text-white font-medium">Base SKU</td>
                    <td class="p-3 border border-slate-700 font-mono text-xs">base sku, base_sku, parent sku</td>
                    <td class="p-3 border border-slate-700 text-sm">Mã sản phẩm gốc. Dùng nếu không có SKU biến thể.</td>
                </tr>
                <tr>
                    <td class="p-3 border border-slate-700 text-white font-medium">Design URL</td>
                    <td class="p-3 border border-slate-700 font-mono text-xs">design url, design_url, link design</td>
                    <td class="p-3 border border-slate-700 text-sm">Link file thiết kế (Drive, Dropbox...).</td>
                </tr>
                <tr>
                    <td class="p-3 border border-slate-700 text-white font-medium">Mockup URL</td>
                    <td class="p-3 border border-slate-700 font-mono text-xs">mockup url, mockup_url, image</td>
                    <td class="p-3 border border-slate-700 text-sm">Link ảnh mockup chính.</td>
                </tr>
            </tbody>
        </table>

        <h2>2. Cấp quyền truy cập (Quan trọng)</h2>
        <p>Để hệ thống đọc được file Sheet của bạn, bạn cần chia sẻ quyền <strong>Viewer (Người xem)</strong> cho email Service Account của hệ thống.</p>
        <ol class="list-decimal pl-6 space-y-2 mt-2">
            <li>Mở file Sheet.</li>
            <li>Nhấn nút <strong>Share (Chia sẻ)</strong>.</li>
            <li>Nhập email Service Account (liên hệ Admin nếu bạn không biết).</li>
            <li>Chọn quyền <strong>Viewer</strong>.</li>
            <li>Nhấn <strong>Share</strong>.</li>
        </ol>

        <h2>3. Cập nhật Link vào Team</h2>
        <p>Sau khi có file Sheet, copy đường link (URL) của nó và cập nhật vào cấu hình Team của bạn thông qua nút "Add Product Sheet URL" hoặc "Edit" ở trang Team Management.</p>
        
        <h2>4. Kích hoạt Sync</h2>
        <p>Để bắt đầu đồng bộ, bạn chỉ cần nhấn nút <strong>"Sync Products"</strong> trong bảng điều khiển của Team.</p>
    `,
    'orders': `
        <h1>📦 Quản lý Orders</h1>
        <p>Module Orders giúp bạn theo dõi và xử lý đơn hàng từ nhiều nguồn (Walmart, Shopify) trong một giao diện tập trung.</p>

        <h2>🔄 Quy trình Xử lý Đơn hàng</h2>
        <div class="my-4 p-4 bg-slate-800/30 rounded-lg border border-slate-700">
            <div class="flex items-center gap-2 text-sm">
                <span class="px-3 py-1 bg-blue-500/20 text-blue-400 rounded">PENDING</span>
                <span>→</span>
                <span class="px-3 py-1 bg-indigo-500/20 text-indigo-400 rounded">DESIGNING</span>
                <span>→</span>
                <span class="px-3 py-1 bg-amber-500/20 text-amber-400 rounded">REVIEW</span>
                <span>→</span>
                <span class="px-3 py-1 bg-emerald-500/20 text-emerald-400 rounded">PRODUCTION</span>
                <span>→</span>
                <span class="px-3 py-1 bg-violet-500/20 text-violet-400 rounded">SHIPPED</span>
            </div>
        </div>

        <h2>📋 Các Chức năng Chính</h2>
        <ul class="list-disc pl-6 space-y-2 mt-2">
            <li><strong>Sync Orders:</strong> Đồng bộ đơn hàng mới từ Walmart/Shopify.</li>
            <li><strong>Filter & Search:</strong> Lọc theo trạng thái, tìm kiếm theo Order ID hoặc SKU.</li>
            <li><strong>Update Status:</strong> Cập nhật trạng thái đơn hàng khi hoàn thành từng bước.</li>
            <li><strong>View Details:</strong> Xem chi tiết sản phẩm, địa chỉ giao hàng, thông tin khách.</li>
        </ul>

        <h2>⚡ Tips</h2>
        <ul class="list-disc pl-6 space-y-2 mt-2">
            <li>Sync orders thường xuyên để không bỏ lỡ đơn mới.</li>
            <li>Sử dụng filter để tập trung vào đơn cần xử lý.</li>
            <li>Cập nhật trạng thái kịp thời để team theo dõi tiến độ.</li>
        </ul>
    `,
    'walmart-sync': `
        <h1>🛒 Đồng bộ Walmart</h1>
        <p>Hệ thống tích hợp với Walmart Marketplace API để tự động đồng bộ đơn hàng và sản phẩm.</p>

        <h2>⚙️ Cấu hình Store Walmart</h2>
        <ol class="list-decimal pl-6 space-y-2 mt-2">
            <li>Vào tab <strong>Settings</strong> → Click <strong>Add New Store</strong>.</li>
            <li>Chọn Platform: <strong>Walmart</strong>.</li>
            <li>Nhập thông tin:
                <ul class="list-disc pl-6 mt-2">
                    <li><strong>Store Name:</strong> Tên gợi nhớ (VD: Walmart Main Store)</li>
                    <li><strong>Store ID:</strong> Mã định danh unique</li>
                    <li><strong>Client ID:</strong> Lấy từ Walmart Developer Portal</li>
                    <li><strong>Client Secret:</strong> Lấy từ Walmart Developer Portal</li>
                </ul>
            </li>
            <li>Đánh dấu <strong>Active</strong> và nhấn <strong>Save</strong>.</li>
        </ol>

        <h2>🔄 Đồng bộ Orders</h2>
        <p>Có 2 cách đồng bộ:</p>
        <ul class="list-disc pl-6 space-y-2 mt-2">
            <li><strong>Manual:</strong> Vào tab Orders → Click <strong>Sync Walmart</strong>.</li>
            <li><strong>Tự động:</strong> Hệ thống có thể tự động sync theo lịch (cần cấu hình cron).</li>
        </ul>

        <h2>📦 Đồng bộ Products</h2>
        <p>Để đồng bộ catalog sản phẩm từ Walmart:</p>
        <ol class="list-decimal pl-6 space-y-2 mt-2">
            <li>Vào tab <strong>Products</strong>.</li>
            <li>Click <strong>Sync Walmart Products</strong>.</li>
            <li>Hệ thống sẽ fetch toàn bộ sản phẩm từ store và lưu vào database.</li>
        </ol>

        <h2>⚠️ Lưu ý</h2>
        <ul class="list-disc pl-6 space-y-2 mt-2">
            <li>Client ID và Secret phải được cấp quyền đầy đủ từ Walmart.</li>
            <li>Sync products có thể mất thời gian nếu catalog lớn.</li>
            <li>Kiểm tra logs nếu gặp lỗi authentication.</li>
        </ul>
    `,
    'shopify-sync': `
        <h1>🛍️ Đồng bộ Shopify</h1>
        <p>Tích hợp với Shopify Admin API để quản lý đơn hàng và sản phẩm từ cửa hàng Shopify.</p>

        <h2>⚙️ Cấu hình Store Shopify</h2>
        <ol class="list-decimal pl-6 space-y-2 mt-2">
            <li>Vào tab <strong>Settings</strong> → Click <strong>Add New Store</strong>.</li>
            <li>Chọn Platform: <strong>Shopify</strong>.</li>
            <li>Nhập thông tin:
                <ul class="list-disc pl-6 mt-2">
                    <li><strong>Store Name:</strong> Tên gợi nhớ</li>
                    <li><strong>Shop URL:</strong> Địa chỉ myshopify.com (VD: chillboard-store.myshopify.com)</li>
                    <li><strong>Access Token:</strong> Admin API access token (bắt đầu với shpat_...)</li>
                </ul>
            </li>
            <li>Đánh dấu <strong>Active</strong> và nhấn <strong>Save</strong>.</li>
        </ol>

        <h2>🔑 Lấy Access Token</h2>
        <ol class="list-decimal pl-6 space-y-2 mt-2">
            <li>Đăng nhập Shopify Admin.</li>
            <li>Vào <strong>Settings</strong> → <strong>Apps and sales channels</strong>.</li>
            <li>Click <strong>Develop apps</strong> → <strong>Create an app</strong>.</li>
            <li>Cấp quyền: <code class="bg-slate-800 px-2 py-1 rounded">read_orders, read_products</code>.</li>
            <li>Install app và copy <strong>Admin API access token</strong>.</li>
        </ol>

        <h2>🔄 Đồng bộ Dữ liệu</h2>
        <p>Tương tự Walmart, bạn có thể sync orders và products từ giao diện hoặc qua API.</p>

        <h2>💡 Best Practices</h2>
        <ul class="list-disc pl-6 space-y-2 mt-2">
            <li>Sử dụng Private App để bảo mật tốt hơn.</li>
            <li>Chỉ cấp quyền tối thiểu cần thiết.</li>
            <li>Rotate access token định kỳ.</li>
        </ul>
    `,
    'team-management': `
        <h1>👥 Quản lý Teams</h1>
        <p>Module Teams giúp tổ chức sellers thành các nhóm, phân quyền stores, và quản lý workflow hiệu quả.</p>

        <h2>🎯 Tạo Team mới (Admin)</h2>
        <ol class="list-decimal pl-6 space-y-2 mt-2">
            <li>Vào tab <strong>Teams</strong> → Click <strong>Create New Team</strong>.</li>
            <li>Nhập thông tin:
                <ul class="list-disc pl-6 mt-2">
                    <li><strong>Team Name:</strong> Tên team (VD: Phoenix Squad)</li>
                    <li><strong>Leader Email:</strong> Email của Team Leader (sẽ tự tạo user nếu chưa có)</li>
                    <li><strong>Product Sheet URL:</strong> (Optional) Link Google Sheet để sync designs</li>
                </ul>
            </li>
            <li>Nhấn <strong>Create Team</strong>.</li>
        </ol>

        <h2>👤 Thêm Members (Leader)</h2>
        <p>Team Leader có thể thêm sellers vào team:</p>
        <ol class="list-decimal pl-6 space-y-2 mt-2">
            <li>Vào Team dashboard → Click <strong>Add Member</strong>.</li>
            <li>Nhập Name, Email, Password.</li>
            <li>Member sẽ được tạo với role <strong>Seller</strong>.</li>
        </ol>

        <h2>🔑 Phân quyền Stores (Leader)</h2>
        <p>Leader có thể delegate quyền truy cập stores cho members:</p>
        <ol class="list-decimal pl-6 space-y-2 mt-2">
            <li>Click <strong>Delegate Access</strong>.</li>
            <li>Chọn Seller, Store, và Permission level (View/Edit).</li>
            <li>Seller sẽ chỉ thấy orders/products từ stores được phân quyền.</li>
        </ol>

        <h2>📊 Team Dashboard</h2>
        <p>Leader có thể xem:</p>
        <ul class="list-disc pl-6 space-y-2 mt-2">
            <li>Danh sách members và roles.</li>
            <li>Stores được assign cho team.</li>
            <li>Product Sheet URL và nút Sync.</li>
        </ul>
    `,
    'store-config': `
        <h1>⚙️ Cấu hình Stores</h1>
        <p>Stores là nguồn dữ liệu đơn hàng và sản phẩm. Mỗi store đại diện cho một kênh bán hàng (Walmart, Shopify, etc.).</p>

        <h2>➕ Thêm Store mới</h2>
        <ol class="list-decimal pl-6 space-y-2 mt-2">
            <li>Vào tab <strong>Settings</strong>.</li>
            <li>Click <strong>Add New Store</strong>.</li>
            <li>Chọn Platform và điền thông tin credentials.</li>
            <li>Đánh dấu <strong>Active</strong> để kích hoạt.</li>
            <li>Nhấn <strong>Save Configuration</strong>.</li>
        </ol>

        <h2>✏️ Chỉnh sửa Store</h2>
        <p>Hover vào store card → Click icon <strong>Edit</strong> → Cập nhật thông tin → Save.</p>

        <h2>🗑️ Xóa Store</h2>
        <p>Hover vào store card → Click icon <strong>Trash</strong> → Confirm.</p>
        <p class="text-amber-400 mt-2">⚠️ Lưu ý: Xóa store không xóa orders/products đã sync, chỉ ngắt kết nối.</p>

        <h2>🔐 Bảo mật Credentials</h2>
        <ul class="list-disc pl-6 space-y-2 mt-2">
            <li>Credentials được mã hóa trong database.</li>
            <li>Chỉ Admin mới có quyền xem/sửa store credentials.</li>
            <li>Không share credentials qua email hoặc chat.</li>
        </ul>

        <h2>📊 Monitoring</h2>
        <p>Kiểm tra trạng thái store thường xuyên:</p>
        <ul class="list-disc pl-6 space-y-2 mt-2">
            <li><span class="text-emerald-500">●</span> <strong>Active:</strong> Store đang hoạt động bình thường.</li>
            <li><span class="text-slate-600">●</span> <strong>Inactive:</strong> Store bị tắt, không sync dữ liệu.</li>
        </ul>
    `
};

function loadWikiContent(topic) {
    const container = document.getElementById('wiki-content');
    if (!container) return;
    const content = WIKI_ARTICLES[topic];
    if (content) {
        container.innerHTML = content;
        container.scrollTop = 0;
    } else {
        container.innerHTML = '<div class="text-center py-20 text-slate-500">Guide not found: ' + topic + '</div>';
    }
}

// ===== DASHBOARD DATA =====
let currentPeriod = 'today';
const periodLabels = {
    today: 'Today', yesterday: 'Yesterday', last7Days: 'Last 7 Days',
    thisMonth: 'This Month', lastMonth: 'Last Month'
};

function changePeriod(period) {
    currentPeriod = period;
    document.querySelectorAll('.period-btn').forEach(btn => btn.classList.remove('active'));
    const btn = document.getElementById(`period-${period}`);
    if (btn) btn.classList.add('active');
    loadDashboardAnalytics();
}

async function loadDashboardAnalytics() {
    try {
        const response = await fetch(`${API_BASE}/analytics/dashboard?period=${currentPeriod}`, { headers: getAuthHeaders() });
        const data = await response.json();
        if (!data.success || !data.data) return;

        const d = data.data;
        const ps = d.periodStats || {};
        const label = periodLabels[currentPeriod] || currentPeriod;

        // Date range
        const rangeEl = document.getElementById('dashboard-date-range');
        if (rangeEl && d.dateRange) {
            rangeEl.textContent = `${d.dateRange.start} - ${d.dateRange.end}`;
        }

        // Financial summary
        document.getElementById('stat-revenue').textContent = '$' + (ps.totalRevenue || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('stat-profit').textContent = '$' + (ps.totalProfit || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('stat-avg-order').textContent = '$' + (ps.avgOrderValue || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('stat-margin').textContent = (ps.profitMargin || 0).toFixed(1) + '%';
        document.getElementById('period-orders-badge').textContent = (ps.totalOrders || 0) + ' orders';

        // Order status counts (all-time)
        const status = d.ordersByStatus || {};
        document.getElementById('stat-pending').textContent = status.pending || 0;
        document.getElementById('stat-designing').textContent = status.designing || 0;
        document.getElementById('stat-review').textContent = status.review || 0;
        document.getElementById('stat-production').textContent = status.production || 0;
        document.getElementById('stat-shipped').textContent = (status.shipped || 0) + (status.delivered || 0);
        document.getElementById('stat-total').textContent = status.total || 0;

        // Seller breakdown
        const sellerLabel = document.getElementById('seller-period-label');
        if (sellerLabel) sellerLabel.textContent = label;
        const sellerTbody = document.getElementById('seller-breakdown-tbody');
        const sellers = d.sellerBreakdown || [];
        if (sellers.length === 0) {
            sellerTbody.innerHTML = '<tr><td colspan="5" class="px-5 py-6 text-center text-slate-400">No data for this period</td></tr>';
        } else {
            sellerTbody.innerHTML = sellers.map(s => `
                <tr class="hover:bg-slate-50 transition-colors border-t border-slate-100">
                    <td class="px-5 py-3">
                        <span class="font-semibold text-slate-900">${s.sellerName || s.sellerCode}</span>
                        ${s.sellerName !== s.sellerCode ? `<span class="text-xs text-slate-400 ml-1">(${s.sellerCode})</span>` : ''}
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-slate-700">${s.totalOrders}</td>
                    <td class="px-5 py-3 text-right font-semibold text-emerald-600">$${s.totalRevenue.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="px-5 py-3 text-right font-medium ${s.totalProfit > 0 ? 'text-blue-600' : 'text-slate-400'}">$${s.totalProfit.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    <td class="px-5 py-3 text-right text-slate-500">$${s.avgOrderValue.toFixed(2)}</td>
                </tr>
            `).join('');
        }

        // Top products
        const tpLabel = document.getElementById('top-products-period-label');
        if (tpLabel) tpLabel.textContent = label;
        const tpTbody = document.getElementById('top-products-tbody');
        const products = d.topProducts || [];
        if (products.length === 0) {
            tpTbody.innerHTML = '<tr><td colspan="4" class="px-5 py-6 text-center text-slate-400">No products for this period</td></tr>';
        } else {
            tpTbody.innerHTML = products.map((p, i) => `
                <tr class="hover:bg-slate-50 transition-colors border-t border-slate-100">
                    <td class="px-5 py-3 text-slate-400 font-mono text-xs">${i + 1}</td>
                    <td class="px-5 py-3">
                        <div class="max-w-[250px]">
                            <p class="font-medium text-slate-900 truncate" title="${p.productName}">${p.productName}</p>
                            <p class="text-xs text-slate-400 font-mono">${p.baseSKU} &middot; ${p.sellerCode}</p>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-slate-700">${p.totalOrders} <span class="text-xs text-slate-400">(${p.totalQuantity || p.totalOrders} pcs)</span></td>
                    <td class="px-5 py-3 text-right font-semibold text-emerald-600">$${p.totalRevenue.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                </tr>
            `).join('');
        }

    } catch (e) { console.error('Dashboard analytics error', e); }
}

async function loadNews() {
    const feed = document.getElementById('news-feed');
    try {
        const response = await fetch(`${API_BASE}/news`, { headers: getAuthHeaders() });
        const data = await response.json();
        if (data.data) {
            feed.innerHTML = data.data.map(item => `
                <div class="p-4 rounded-xl bg-slate-800/20 border border-slate-700/50 flex gap-4 animate-fade-in group hover:border-vibrant-primary/30 transition-all">
                    <div class="w-10 h-10 rounded-lg bg-vibrant-primary/10 flex items-center justify-center text-vibrant-primary">
                        <i class="fas ${item.source === 'walmart' ? 'fa-shopping-bag' : 'fa-search'}"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex justify-between">
                            <h4 class="font-semibold text-white">${item.title}</h4>
                            <span class="text-xs text-slate-500">${new Date(item.event_timestamp).toLocaleTimeString()}</span>
                        </div>
                        <p class="text-sm text-slate-400 mt-1">${item.message}</p>
                        <div class="mt-2 text-xs font-mono text-slate-500">${item.description || ''}</div>
                    </div>
                </div>
            `).join('') || '<div class="text-center text-slate-500 py-10">No recent activity.</div>';
        }
    } catch (e) { feed.innerHTML = 'Error loading news.'; }
}

async function loadMiniTrends() {
    const container = document.getElementById('mini-trends');
    try {
        const response = await fetch(`${API_BASE}/trends?limit=5`, { headers: getAuthHeaders() });
        const data = await response.json();
        if (data.data) {
            container.innerHTML = data.data.map(t => `
                <div class="flex items-center justify-between group cursor-pointer">
                    <div class="flex-1">
                        <h4 class="text-sm font-medium text-slate-300 group-hover:text-vibrant-primary transition-colors">${t.title}</h4>
                        <p class="text-xs text-slate-500">${t.source.toUpperCase()}</p>
                    </div>
                    <span class="text-xs font-bold text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded-full">+${t.trending_score}%</span>
                </div>
            `).join('') || '<div class="text-center text-slate-500 py-4">No trends available.</div>';
        }
    } catch (e) { container.innerHTML = 'Error.'; }
}

async function loadRecentOrders() {
    const tbody = document.getElementById('dashboard-orders-tbody');
    if (!tbody) return;
    try {
        const response = await fetch(`${API_BASE}/orders?per_page=8`, { headers: getAuthHeaders() });
        const data = await response.json();
        if (data.success && data.data) {
            tbody.innerHTML = data.data.map(order => {
                const status = (order.status || 'PENDING').toUpperCase();
                return `
                    <tr class="group border-t border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-3 font-mono text-sm text-vibrant-primary">#${order.orderID || order.id}</td>
                        <td class="px-5 py-3">
                            <div class="max-w-[200px]">
                                <p class="text-sm font-medium text-slate-900 truncate">${order.productName || 'Unnamed Product'}</p>
                                <p class="text-xs text-slate-400">${order.platform || 'Walmart'}</p>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-500 font-mono">${order.sku || 'N/A'}</td>
                        <td class="px-5 py-3 text-right font-semibold text-slate-900">$${(order.total || 0).toFixed(2)}</td>
                        <td class="px-5 py-3">
                            <span class="stage-badge stage-${status}">${status}</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button onclick="viewOrder(${order.id})" class="p-2 text-slate-400 hover:text-vibrant-primary transition-colors">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('') || '<tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No orders found.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-5 py-8 text-center text-red-400">Error loading recent orders.</td></tr>';
    }
}


// ===== PRODUCTS =====
async function loadProducts() {
    const tbody = document.getElementById('products-tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading products...</td></tr>';
    try {
        const response = await fetch(`${API_BASE}/products`, { headers: getAuthHeaders() });
        const data = await response.json();

        if (data.success && data.data) {
            // Update Stats
            document.getElementById('product-stat-total').textContent = data.data.length;

            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-slate-500">No products found.</td></tr>';
                return;
            }

            tbody.innerHTML = data.data.map(p => renderProductRow(p)).join('');
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="py-10 text-center text-red-400">Error loading products.</td></tr>';
    }
}

function renderProductRow(p) {
    const img = p.Image || 'https://via.placeholder.com/80';
    return `
        <tr>
            <td>
                <div class="flex items-center gap-3">
                    <img src="${img}" class="w-10 h-10 rounded-lg object-cover bg-slate-50 border border-slate-100 shadow-sm">
                    <div class="max-w-[200px]">
                        <p class="text-sm font-bold text-slate-900 truncate" title="${p.Title || 'Unnamed Product'}">${p.Title || 'Unnamed Product'}</p>
                    </div>
                </div>
            </td>
            <td><span class="font-mono text-xs font-bold text-indigo-600">${p.SKU}</span></td>
            <td><span class="text-slate-500 font-medium">${p.Seller || 'Direct'}</span></td>
            <td><span class="text-slate-400 font-mono text-xs">${p.store_id || '---'}</span></td>
            <td class="font-bold text-slate-900">$${p.Price || 0}</td>
            <td>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-[10px] font-bold text-slate-600 border border-slate-200 uppercase">
                    ${p.Status || 'Active'}
                </span>
            </td>
            <td class="text-right">
                <div class="flex justify-end gap-2">
                    <button onclick="editProduct(${p.id})" class="p-2 text-slate-400 hover:text-indigo-600 transition-colors"><i class="fas fa-edit"></i></button>
                    <button onclick="deleteProduct(${p.id})" class="p-2 text-slate-400 hover:text-rose-600 transition-colors"><i class="fas fa-trash"></i></button>
                </div>
            </td>
        </tr>
    `;
}

async function syncWalmartProducts() {
    try {
        const response = await fetch(`${API_BASE}/stores`, { headers: getAuthHeaders() });
        const stores = await response.json();
        const wmStores = Array.isArray(stores) ? stores.filter(s => s.active && s.platform.toLowerCase() === 'walmart') : [];

        if (wmStores.length === 0) {
            alert('No active Walmart stores configured.');
            return;
        }

        if (!confirm(`Sync products from ${wmStores.length} Walmart store(s)?`)) return;

        let totalSynced = 0;
        for (const store of wmStores) {
            try {
                const res = await fetch(`${API_BASE}/walmart/sync-products`, {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ storeId: store.store_id })
                });
                const data = await res.json();
                if (data.success) totalSynced += data.count;
            } catch (e) {
                console.error(`Sync failed for ${store.store_name}`, e);
            }
        }

        alert(`Sync complete. Imported ${totalSynced} products.`);
        loadProducts();
    } catch (e) {
        alert('Sync Trigger Failed');
    }
}

function openProductModal(id = null) {
    const modal = document.getElementById('product-modal');
    const form = document.getElementById('product-form');
    form.reset();
    document.getElementById('product-db-id').value = '';

    if (id) {
        fetch(`${API_BASE}/products/${id}`, { headers: getAuthHeaders() })
            .then(res => res.json())
            .then(p => {
                document.getElementById('product-db-id').value = p.id;
                document.getElementById('product-sku').value = p.sku;
                document.getElementById('product-title').value = p.title || '';
                document.getElementById('product-seller').value = p.seller_code || '';
                document.getElementById('product-base-sku').value = p.base_sku || '';
            });
    }
    modal.classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('product-modal').classList.add('hidden');
}

function editProduct(id) {
    openProductModal(id);
}

document.getElementById('product-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('product-db-id').value;
    const data = {
        title: document.getElementById('product-title').value,
        seller_code: document.getElementById('product-seller').value,
        base_sku: document.getElementById('product-base-sku').value
    };

    try {
        const response = await fetch(`${API_BASE}/products/${id}`, {
            method: 'PUT',
            headers: getAuthHeaders(),
            body: JSON.stringify(data)
        });

        if (response.ok) {
            closeProductModal();
            loadProducts();
        } else {
            alert('Failed to save product');
        }
    } catch (e) { alert('Connection error'); }
});

async function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    try {
        const response = await fetch(`${API_BASE}/products/${id}`, {
            method: 'DELETE',
            headers: getAuthHeaders()
        });
        if (response.ok) loadProducts();
        else alert('Delete failed');
    } catch (e) { alert('Connection error'); }
}

// ===== ORDERS =====
let orderSearchTimer = null;
let orderCurrentPage = 1;
let knownSellerCodes = new Set();

function debounceOrderSearch() {
    clearTimeout(orderSearchTimer);
    orderSearchTimer = setTimeout(() => loadOrders(1), 400);
}

async function loadOrders(page) {
    if (page) orderCurrentPage = page;
    const tbody = document.getElementById('orders-tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading orders...</td></tr>';

    const params = new URLSearchParams();
    params.set('page', orderCurrentPage);
    params.set('per_page', 30);

    const status = document.getElementById('order-status-filter')?.value;
    if (status) params.set('status', status);

    const seller = document.getElementById('order-seller-filter')?.value;
    if (seller) params.set('seller_code', seller);

    const search = document.getElementById('order-search')?.value?.trim();
    if (search) params.set('search', search);

    const startDate = document.getElementById('order-start-date')?.value;
    const endDate = document.getElementById('order-end-date')?.value;
    if (startDate && endDate) {
        params.set('startDate', startDate);
        params.set('endDate', endDate);
    }

    try {
        const response = await fetch(`${API_BASE}/orders?${params}`, { headers: getAuthHeaders() });
        const data = await response.json();
        if (data.success && data.data) {
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-slate-400">No orders found.</td></tr>';
            } else {
                tbody.innerHTML = data.data.map(order => renderOrderRow(order)).join('');
                data.data.forEach(o => { if (o.sellerCode) knownSellerCodes.add(o.sellerCode); });
                populateSellerFilter();
            }

            const label = document.getElementById('orders-count-label');
            if (label && data.meta) label.textContent = `${data.meta.total} orders`;

            if (data.meta) renderOrdersPagination(data.meta);
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-red-400">Error loading orders.</td></tr>';
    }
}

function populateSellerFilter() {
    const sel = document.getElementById('order-seller-filter');
    if (!sel) return;
    const current = sel.value;
    const codes = Array.from(knownSellerCodes).sort();
    if (sel.options.length - 1 >= codes.length) return;
    sel.innerHTML = '<option value="">All Sellers</option>' +
        codes.map(c => `<option value="${c}" ${c === current ? 'selected' : ''}>${c}</option>`).join('');
}

function renderOrdersPagination(meta) {
    const container = document.getElementById('orders-pagination');
    if (!container) return;
    const { current_page, last_page, total } = meta;
    if (last_page <= 1) { container.innerHTML = ''; return; }

    let pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) pages.push(i);

    container.innerHTML = `
        <div class="flex items-center gap-1">
            <button onclick="loadOrders(1)" class="px-3 py-1.5 rounded text-xs font-medium ${current_page === 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current_page === 1 ? 'disabled' : ''}><i class="fas fa-angle-double-left"></i></button>
            <button onclick="loadOrders(${current_page - 1})" class="px-3 py-1.5 rounded text-xs font-medium ${current_page === 1 ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current_page === 1 ? 'disabled' : ''}><i class="fas fa-angle-left"></i></button>
            ${pages.map(p => `<button onclick="loadOrders(${p})" class="px-3 py-1.5 rounded text-xs font-medium ${p === current_page ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100'}">${p}</button>`).join('')}
            <button onclick="loadOrders(${current_page + 1})" class="px-3 py-1.5 rounded text-xs font-medium ${current_page === last_page ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current_page === last_page ? 'disabled' : ''}><i class="fas fa-angle-right"></i></button>
            <button onclick="loadOrders(${last_page})" class="px-3 py-1.5 rounded text-xs font-medium ${current_page === last_page ? 'text-slate-300 cursor-not-allowed' : 'text-slate-600 hover:bg-slate-100'}" ${current_page === last_page ? 'disabled' : ''}><i class="fas fa-angle-double-right"></i></button>
        </div>
        <span class="text-xs text-slate-400">Page ${current_page} of ${last_page} (${total} total)</span>
    `;
}

function renderOrderRow(order) {
    const status = (order.status || 'PENDING').toUpperCase();
    const isPending = status === 'PENDING';
    const date = order.date ? new Date(order.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '—';

    return `
        <tr class="group hover:bg-slate-50 transition-all">
            <td>
                <input type="checkbox" value="${order.id}" onchange="onOrderSelectChange()"
                    class="order-checkbox rounded border-slate-300 bg-white text-indigo-600 focus:ring-indigo-500">
            </td>
            <td>
                <a href="#" onclick="viewOrder(${order.id}); return false;" class="table-link">#${order.orderID || order.id}</a>
                <div class="text-[10px] text-slate-400">${order.storeId || ''}</div>
            </td>
            <td class="text-slate-500 text-xs">${date}</td>
            <td>
                <div class="col-multi-line max-w-[220px]">
                    <span class="col-title truncate" title="${order.productName || ''}">${order.productName || 'Unnamed Product'}</span>
                    <span class="col-subtitle text-xs">${order.platform || 'Walmart'} &middot; ${order.sellerCode || '—'}</span>
                </div>
            </td>
            <td class="text-slate-500 font-mono text-xs">${order.sku || 'N/A'}</td>
            <td class="text-slate-900 font-semibold text-center">${order.quantity || 1}</td>
            <td class="text-slate-900 font-bold">$${(order.total || 0).toFixed(2)}</td>
            <td>
                <span class="stage-badge stage-${status}">${status}</span>
            </td>
            <td class="text-right">
                <div class="flex justify-end gap-2">
                    <button onclick="viewOrder(${order.id})" class="btn-icon" title="View Details">
                        <i class="fas fa-eye text-sm"></i>
                    </button>
                    ${isPending ? `
                        <button onclick="fulfillOrder('${order.orderID}')" class="btn-icon" title="Fulfill Now">
                            <i class="fas fa-box text-sm"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `;
}

// ===== TRENDS PAGE =====
async function loadTrends() {
    const grid = document.getElementById('trends-grid');
    grid.innerHTML = '<div class="col-span-4 py-20 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i> Fetching market data...</div>';
    try {
        const response = await fetch(`${API_BASE}/trends`, { headers: getAuthHeaders() });
        const result = await response.json();
        if (result.data) {
            grid.innerHTML = result.data.map(t => `
                <div class="glass-card p-6 flex flex-col gap-4 group hover:border-vibrant-primary/50 transition-all cursor-pointer">
                    <div class="flex justify-between">
                         <span class="text-xs font-bold uppercase tracking-wider text-vibrant-primary">${t.source}</span>
                         <span class="text-emerald-500 text-xs font-bold leading-none bg-emerald-500/10 px-1.5 py-1 rounded">+${t.trending_score}%</span>
                    </div>
                    <h4 class="text-lg font-bold text-white leading-tight">${t.title}</h4>
                    <p class="text-sm text-slate-400 line-clamp-2">${t.description || 'Rising interest detected in this niche.'}</p>
                    <div class="mt-auto flex items-center justify-between border-t border-slate-800 pt-4">
                         <div class="flex -space-x-2">
                            <div class="w-6 h-6 rounded-full bg-slate-700 border-2 border-navy-900"></div>
                            <div class="w-6 h-6 rounded-full bg-slate-600 border-2 border-navy-900"></div>
                         </div>
                         <button class="text-xs text-slate-500 hover:text-white transition-colors">Analyze <i class="fas fa-arrow-right ml-1"></i></button>
                    </div>
                </div>
            `).join('');
        }
    } catch (e) { grid.innerHTML = 'Error.'; }
}

async function syncOrders() {
    try {
        const response = await fetch(`${API_BASE}/stores`, { headers: getAuthHeaders() });
        const stores = await response.json();
        const activeStores = Array.isArray(stores) ? stores.filter(s => s.active) : [];

        if (activeStores.length === 0) {
            alert('No active stores configured to sync.');
            return;
        }

        if (!confirm(`Sync orders from ${activeStores.length} active store(s)?`)) return;

        let successCount = 0;
        for (const store of activeStores) {
            const platform = store.platform.toLowerCase();
            const endpoint = platform === 'shopify' ? 'shopify/sync' : 'walmart/sync';

            try {
                const res = await fetch(`${API_BASE}/${endpoint}`, {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ storeId: store.store_id, days: 7 })
                });
                if (res.ok) successCount++;
            } catch (e) {
                console.error(`Sync failed for ${store.store_name}`, e);
            }
        }

        alert(`Sync complete. Successfully triggered for ${successCount}/${activeStores.length} stores.`);
        loadOrders();
    } catch (e) {
        alert('Sync Trigger Failed');
    }
}

// ===== SETTINGS / STORE MANAGEMENT =====
async function loadStores() {
    const grid = document.getElementById('stores-grid');
    grid.innerHTML = '<div class="col-span-3 text-center py-10 text-slate-500"><i class="fas fa-spinner fa-spin"></i> Loading stores...</div>';

    try {
        const response = await fetch(`${API_BASE}/stores`, { headers: getAuthHeaders() });
        const stores = await response.json();

        if (!response.ok) {
            grid.innerHTML = '<div class="col-span-3 text-center text-red-400">Failed to load stores.</div>';
            return;
        }

        // Stats
        document.getElementById('store-stat-total').textContent = stores.length;
        document.getElementById('store-stat-walmart').textContent = stores.filter(s => s.platform === 'walmart').length;
        document.getElementById('store-stat-shopify').textContent = stores.filter(s => s.platform === 'shopify').length;

        if (stores.length === 0) {
            grid.innerHTML = '<div class="col-span-3 text-center text-slate-500 py-10">No stores configured. Add one to get started.</div>';
            return;
        }

        grid.innerHTML = stores.map(store => `
            <div class="glass-card p-6 flex flex-col gap-6 relative group overflow-hidden border-transparent hover:border-slate-200 transition-all">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl shadow-sm border border-slate-100
                            ${store.platform === 'shopify' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600'}">
                            <i class="fab fa-${store.platform === 'shopify' ? 'shopify' : 'superpowers'}"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-slate-900">${store.store_name}</h4>
                            <span class="text-xs text-slate-400 font-mono tracking-tighter uppercase">${store.platform} Channel</span>
                        </div>
                    </div>
                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button onclick="editStore(${store.id})" class="p-2 text-slate-400 hover:text-blue-600 transition-colors"><i class="fas fa-edit"></i></button>
                        <button onclick="deleteStore(${store.id})" class="p-2 text-slate-400 hover:text-rose-600 transition-colors"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500 font-medium">Store ID</span>
                        <span class="text-slate-900 font-mono font-bold">${store.store_id}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-500 text-sm font-medium">Status</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase 
                            ${store.active ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200'}">
                            ${store.active ? 'Online' : 'Offline'}
                        </span>
                    </div>
                </div>
                
                <div class="pt-4 border-t border-slate-50 flex items-center justify-between">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Credentials</span>
                    <span class="text-[10px] text-emerald-600 font-bold bg-emerald-50 px-2 py-0.5 rounded">ENCRYPTED</span>
                </div>
            </div>
        `).join('');

    } catch (e) {
        console.error(e);
        grid.innerHTML = '<div class="col-span-3 text-center text-red-400">Error loading stores.</div>';
    }
}

function openStoreModal(store = null) {
    const modal = document.getElementById('store-modal');
    modal.classList.remove('hidden');

    if (store) {
        document.getElementById('modal-title').textContent = 'Edit Store';
        document.getElementById('store-db-id').value = store.id;
        document.getElementById('store-platform').value = store.platform;
        document.getElementById('store-name').value = store.store_name;
        document.getElementById('store-id').value = store.store_id;
        document.getElementById('store-active').checked = store.active;

        updateCredentialFields(store.credentials);
    } else {
        document.getElementById('modal-title').textContent = 'Add New Store';
        document.getElementById('store-db-id').value = '';
        document.getElementById('store-form').reset();
        document.getElementById('store-active').checked = true;
        updateCredentialFields();
    }
}

function closeStoreModal() {
    document.getElementById('store-modal').classList.add('hidden');
}

function updateCredentialFields(existingData = {}) {
    const platform = document.getElementById('store-platform').value;
    const container = document.getElementById('credentials-container');

    if (platform === 'shopify') {
        container.innerHTML = `
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Shop URL (myshopify.com)</label>
                <input type="text" name="shopUrl" class="glass-input-sm w-full" placeholder="chillboard-store.myshopify.com" value="${existingData?.shopUrl || ''}" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Admin Access Token</label>
                <input type="password" name="accessToken" class="glass-input-sm w-full" placeholder="shpat_..." value="${existingData?.accessToken || ''}" required>
            </div>
        `;
    } else if (platform === 'walmart') {
        container.innerHTML = `
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Client ID</label>
                <input type="text" name="clientId" class="glass-input-sm w-full" value="${existingData?.clientId || ''}" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1">Client Secret</label>
                <input type="password" name="clientSecret" class="glass-input-sm w-full" value="${existingData?.clientSecret || ''}" required>
            </div>
        `;
    } else {
        container.innerHTML = '<p class="text-sm text-slate-500">No specific credentials needed for this local test.</p>';
    }
}

document.getElementById('store-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('store-db-id').value;
    const platform = document.getElementById('store-platform').value;

    // Harvest dynamic credentials
    const credentials = {};
    const container = document.getElementById('credentials-container');
    container.querySelectorAll('input').forEach(input => {
        if (input.name) credentials[input.name] = input.value;
    });

    const payload = {
        store_id: document.getElementById('store-id').value,
        store_name: document.getElementById('store-name').value,
        platform: platform,
        active: document.getElementById('store-active').checked,
        credentials: credentials, // Laravel casts this to array/json automatically
        settings: {}
    };

    try {
        const method = id ? 'PUT' : 'POST';
        const url = id ? `${API_BASE}/stores/${id}` : `${API_BASE}/stores`;

        const response = await fetch(url, {
            method: method,
            headers: getAuthHeaders(),
            body: JSON.stringify(payload)
        });

        if (response.ok) {
            alert('Store saved successfully!');
            closeStoreModal();
            loadStores();
        } else {
            const err = await response.json();
            alert('Error: ' + (err.message || JSON.stringify(err)));
        }
    } catch (e) {
        alert('Connection Failed');
    }
});

async function editStore(id) {
    try {
        const response = await fetch(`${API_BASE}/stores/${id}`, { headers: getAuthHeaders() });
        const store = await response.json();
        openStoreModal(store);
    } catch (e) { alert('Could not load store details'); }
}

async function deleteStore(id) {
    if (!confirm('Are you sure you want to delete this store assignment?')) return;
    try {
        await fetch(`${API_BASE}/stores/${id}`, { method: 'DELETE', headers: getAuthHeaders() });
        loadStores();
    } catch (e) { alert('Delete failed'); }
}

// ===== TEAM MANAGEMENT =====
async function loadTeams() {
    const grid = document.getElementById('teams-grid');
    grid.innerHTML = '<div class="text-center py-10 text-slate-500"><i class="fas fa-spinner fa-spin"></i> Loading teams...</div>';

    try {
        const response = await fetch(`${API_BASE}/teams`, { headers: getAuthHeaders() });
        const teams = await response.json();

        if (response.ok) {
            document.getElementById('btn-create-team')?.classList.remove('hidden');

            // Stats
            document.getElementById('team-stat-total').textContent = teams.length;
            document.getElementById('team-stat-active').textContent = teams.length; // Assume active for now
            const totalMembers = teams.reduce((acc, t) => acc + (t.members?.length || 0), 0);
            document.getElementById('team-stat-members').textContent = totalMembers;

            grid.innerHTML = teams.map(team => renderTeamCard(team, true)).join('');
        } else {
            loadMyTeam(grid);
        }
    } catch (e) {
        loadMyTeam(grid);
    }
}

function renderTeamCard(team, isAdmin = false) {
    const membersHtml = team.members?.map(m => `
        <span class="inline-flex items-center px-3 py-1 bg-slate-100 text-slate-600 rounded-md text-sm font-medium">
            ${m.name} ${m.role === 'leader' ? '<span class="text-slate-400 ml-1 text-xs">(Leader)</span>' : team.leader_id === m.id ? '<span class="text-slate-400 ml-1 text-xs">(Leader)</span>' : '<span class="text-slate-400 ml-1 text-xs">(Seller)</span>'}
        </span>
    `).join('') || '<span class="text-slate-400 text-sm">No members</span>';

    const storesHtml = team.stores?.map(s => `
        <span class="text-blue-600 bg-blue-50 px-2 py-0.5 rounded text-sm font-semibold border border-blue-100">
            ${s.store_id || s.name || s.store_name}
        </span>
    `).join('') || '<span class="text-slate-400 text-sm">None configured</span>';

    return `
        <div class="glass-card p-8 relative">
            <div class="flex justify-between items-start mb-6">
                <div class="flex items-center gap-3">
                    <h4 class="text-xl font-bold text-slate-900">${team.name}</h4>
                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded uppercase">Active</span>
                </div>
                <div class="flex gap-2">
                    <button onclick="editTeam(${team.id})" class="text-blue-600 bg-blue-50 px-4 py-1.5 rounded-lg text-sm font-medium border border-blue-100 hover:bg-blue-100 transition-all">Edit</button>
                    <button onclick="deleteTeam(${team.id})" class="text-rose-600 bg-rose-50 px-4 py-1.5 rounded-lg text-sm font-medium border border-rose-100 hover:bg-rose-100 transition-all">Delete</button>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Leader -->
                <div class="space-y-2">
                    <h5 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Leader</h5>
                    <div class="inline-flex items-center gap-2 bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-md text-sm font-medium border border-indigo-100">
                        ${team.leader?.name || 'N/A'} <span class="text-indigo-400 font-normal">${team.leader?.email || ''}</span>
                    </div>
                </div>

                <!-- Members -->
                <div class="space-y-2 relative">
                    <div class="flex justify-between items-center">
                        <h5 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Members (${team.members?.length || 0})</h5>
                        <button onclick="openMemberModal(${team.id})" class="text-blue-600 text-[10px] font-bold hover:underline">+ Add Member</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        ${membersHtml}
                    </div>
                </div>

                <!-- Assigned Stores -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <h5 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Assigned Stores (${team.stores?.length || 0})</h5>
                        <button onclick="openStoreAssignmentModal(${team.id})" class="text-blue-600 text-[10px] font-bold hover:underline">Manage Stores</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        ${storesHtml}
                    </div>
                </div>

                <!-- Google Sheet Sync -->
                <div class="space-y-2">
                    <h5 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Google Sheet Sync</h5>
                    <div class="flex items-center gap-3">
                        <div class="inline-flex items-center gap-2 ${team.product_sheet_url ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-slate-50 text-slate-500 border-slate-100'} px-3 py-1.5 rounded-md text-sm font-medium border">
                            <i class="fas fa-file-excel ${team.product_sheet_url ? 'text-emerald-500' : 'text-slate-400'}"></i> 
                            ${team.product_sheet_url ? 'Configured' : 'Not configured'}
                            ${team.product_sheet_url ? '<span class="px-1.5 py-0.5 bg-emerald-500 text-white text-[9px] rounded font-bold uppercase ml-1">Active</span>' : ''}
                        </div>
                        ${team.product_sheet_url ? `
                            <button onclick="syncProducts(${team.id})" class="inline-flex items-center gap-2 bg-white text-indigo-600 px-4 py-1.5 rounded-md text-sm font-bold border border-indigo-100 hover:bg-indigo-50 transition-all shadow-sm">
                                <i class="fas fa-sync-alt"></i> Sync Now
                            </button>
                        ` : ''}
                    </div>
                </div>

                <!-- Telegram Notifications -->
                <div class="space-y-2">
                    <h5 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Telegram Notifications</h5>
                    <p class="text-sm text-slate-400 italic">Not configured</p>
                </div>
            </div>
        </div>
    `;
}

async function loadMyTeam(container) {
    try {
        const response = await fetch(`${API_BASE}/teams/my`, { headers: getAuthHeaders() });
        const team = await response.json();

        if (team.error) {
            container.innerHTML = `<div class="text-center text-slate-500 py-10">You are not part of any team.</div>`;
            return;
        }

        document.getElementById('btn-create-team')?.classList.add('hidden');
        container.innerHTML = renderTeamCard(team, false);

        // Update stats for leader (team-specific stats)
        document.getElementById('team-stat-total').textContent = '1';
        document.getElementById('team-stat-active').textContent = '1';
        document.getElementById('team-stat-members').textContent = team.members?.length || 0;

        window.currentTeamData = team;
    } catch (e) {
        container.innerHTML = `<div class="text-center text-red-400">Error loading team data.</div>`;
    }
}

// --- MODAL ACTIONS ---

function openTeamModal() {
    document.getElementById('team-modal-title').textContent = 'Create New Team';
    document.getElementById('team-submit-btn').textContent = 'Create Team';
    document.getElementById('team-db-id').value = '';
    document.getElementById('team-form').reset();
    document.getElementById('team-modal').classList.remove('hidden');
}
function closeTeamModal() { document.getElementById('team-modal').classList.add('hidden'); }

async function editTeam(id) {
    try {
        const res = await fetch(`${API_BASE}/teams/${id}`, { headers: getAuthHeaders() });
        const team = await res.json();

        if (team && (team.id || team._id)) {
            document.getElementById('team-db-id').value = team.id || team._id;
            document.getElementById('team-name').value = team.name || '';
            document.getElementById('team-leader-email').value = team.leader?.email || '';
            document.getElementById('team-product-sheet').value = team.product_sheet_url || '';

            document.getElementById('team-modal-title').textContent = 'Edit Team';
            document.getElementById('team-submit-btn').textContent = 'Save Changes';
            document.getElementById('team-modal').classList.remove('hidden');
        } else {
            alert('Error fetching team details');
        }
    } catch (e) {
        alert('Connection error');
    }
}

document.getElementById('team-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('team-db-id').value;
    const name = document.getElementById('team-name').value;
    const email = document.getElementById('team-leader-email').value;
    const sheetUrl = document.getElementById('team-product-sheet').value;

    const method = id ? 'PUT' : 'POST';
    const url = id ? `${API_BASE}/teams/${id}` : `${API_BASE}/teams`;

    try {
        const res = await fetch(url, {
            method: method,
            headers: getAuthHeaders(),
            body: JSON.stringify({ name, leader_email: email, product_sheet_url: sheetUrl })
        });
        if (res.ok) {
            alert(id ? 'Team updated!' : 'Team created!');
            closeTeamModal();
            loadTeams();
        } else {
            const error = await res.json();
            alert(`Error: ${error.message || error.error || 'Operation failed'}`);
        }
    } catch (e) { alert('Connection Error'); }
});

function openMemberModal(teamId) {
    document.getElementById('member-team-id').value = teamId;
    document.getElementById('member-modal').classList.remove('hidden');
}
function closeMemberModal() { document.getElementById('member-modal').classList.add('hidden'); }

document.getElementById('member-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const teamId = document.getElementById('member-team-id').value;
    const body = {
        name: document.getElementById('member-name').value,
        email: document.getElementById('member-email').value,
        password: document.getElementById('member-password').value
    };

    try {
        const res = await fetch(`${API_BASE}/teams/${teamId}/members`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify(body)
        });
        if (res.ok) {
            alert('Member added!');
            closeMemberModal();
            loadTeams(); // Reloads my team view
        } else alert('Failed to add member');
    } catch (e) { alert('Error'); }
});

function openDelegateModal(teamId) {
    const modal = document.getElementById('delegate-modal');
    modal.classList.remove('hidden');

    // Populate dropdowns from cached window.currentTeamData
    const team = window.currentTeamData;
    if (!team) return;

    const userSelect = document.getElementById('delegate-user-id');
    userSelect.innerHTML = team.members.filter(m => m.role !== 'leader').map(m => `<option value="${m.id}">${m.name} (${m.email})</option>`).join('');

    const storeSelect = document.getElementById('delegate-store-id');
    storeSelect.innerHTML = team.stores.map(s => `<option value="${s.id}">${s.store_name}</option>`).join('');

    document.getElementById('delegate-team-id').value = teamId;
}
function closeDelegateModal() { document.getElementById('delegate-modal').classList.add('hidden'); }

document.getElementById('delegate-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const teamId = document.getElementById('delegate-team-id').value;
    const body = {
        user_id: document.getElementById('delegate-user-id').value,
        store_id: document.getElementById('delegate-store-id').value,
        permission: document.querySelector('input[name="perm"]:checked').value
    };

    try {
        const res = await fetch(`${API_BASE}/teams/${teamId}/delegate`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify(body)
        });
        if (res.ok) {
            alert('Access Delegated!');
            closeDelegateModal();
        } else alert('Delegation failed');
    } catch (e) { alert('Error'); }
});


// ===== SELLER MANAGEMENT =====
async function loadSellers() {
    const tbody = document.getElementById('sellers-tbody');
    tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-500"><i class="fas fa-spinner fa-spin"></i> Loading sellers...</td></tr>';

    try {
        const response = await fetch(`${API_BASE}/users`, { headers: getAuthHeaders() });
        const sellers = await response.json();

        if (!response.ok) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-red-500">${sellers.error || 'Failed to load sellers'}</td></tr>`;
            return;
        }

        // Stats
        document.getElementById('seller-stat-total').textContent = sellers.length;
        const assignedCount = sellers.filter(s => s.team_id).length;
        const coverage = sellers.length > 0 ? Math.round((assignedCount / sellers.length) * 100) : 0;
        document.getElementById('seller-stat-teams-coverage').textContent = `${coverage}%`;

        if (sellers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-slate-500">No sellers found.</td></tr>';
            return;
        }

        tbody.innerHTML = sellers.map(seller => {
            const isLeader = seller.leading_team && seller.leading_team.id;
            const initials = seller.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);

            return `
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 border border-slate-200">
                            ${initials}
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-slate-900">${seller.name}</span>
                            <span class="text-[10px] text-slate-400 font-mono uppercase">Code: ${seller.seller_code || '---'}</span>
                        </div>
                    </div>
                </td>
                <td class="text-slate-500 font-medium">${seller.email}</td>
                <td>
                    ${seller.team ?
                    `<span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-xs font-bold rounded border border-indigo-100">${seller.team.name}</span>` :
                    '<span class="text-slate-400 text-xs italic">Unassigned</span>'}
                </td>
                <td>
                    <div class="flex flex-col gap-1">
                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase w-fit
                            ${seller.role === 'admin' ? 'bg-rose-100 text-rose-700 border border-rose-200' :
                    isLeader ? 'bg-amber-100 text-amber-700 border border-amber-200' :
                        'bg-slate-100 text-slate-600 border border-slate-200'}">
                            ${seller.role}
                        </span>
                        ${isLeader ? `<span class="text-[9px] text-amber-600 font-bold uppercase">Leading: ${seller.leading_team.name}</span>` : ''}
                    </div>
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-2">
                        <button onclick="editUser(${seller.id})" class="p-2 text-slate-400 hover:text-blue-600 transition-colors"><i class="fas fa-edit"></i></button>
                        <button onclick="deleteUser(${seller.id})" class="p-2 text-slate-400 hover:text-rose-600 transition-colors"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
            `;
        }).join('');

    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-red-500">Connection error.</td></tr>';
    }
}

async function openUserModal(userId = null) {
    const modal = document.getElementById('user-modal');
    const title = document.getElementById('user-modal-title');
    const form = document.getElementById('user-form');
    const hint = document.getElementById('user-password-hint');
    const teamSelect = document.getElementById('user-team-id');

    form.reset();
    document.getElementById('user-db-id').value = userId || '';

    // Load teams for assignment
    try {
        const res = await fetch(`${API_BASE}/teams`, { headers: getAuthHeaders() });
        const teams = await res.json();
        teamSelect.innerHTML = '<option value="">No Team</option>' +
            teams.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
    } catch (e) { }

    if (userId) {
        title.textContent = 'Edit Seller';
        hint.classList.remove('hidden');
        // Fetch user data
        try {
            const res = await fetch(`${API_BASE}/users/${userId}`, { headers: getAuthHeaders() });
            const user = await res.json();
            document.getElementById('user-name').value = user.name;
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-team-id').value = user.team_id || '';
            document.getElementById('user-seller-code').value = user.seller_code || '';
        } catch (e) { }
    } else {
        title.textContent = 'Create New Seller';
        hint.classList.add('hidden');
    }

    modal.classList.remove('hidden');
}

function closeUserModal() {
    document.getElementById('user-modal').classList.add('hidden');
}

document.getElementById('user-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const userId = document.getElementById('user-db-id').value;
    const method = userId ? 'PUT' : 'POST';
    const url = userId ? `${API_BASE}/users/${userId}` : `${API_BASE}/users`;

    const body = {
        name: document.getElementById('user-name').value,
        email: document.getElementById('user-email').value,
        team_id: document.getElementById('user-team-id').value || null,
        seller_code: document.getElementById('user-seller-code').value || null
    };

    const password = document.getElementById('user-password').value;
    if (password) {
        body.password = password;
    } else if (!userId) {
        // Password is required for new users
        alert('Password is required for new sellers');
        return;
    }

    console.log('Submitting user:', method, url, body);

    try {
        const response = await fetch(url, {
            method,
            headers: getAuthHeaders(),
            body: JSON.stringify(body)
        });

        console.log('Response status:', response.status, response.statusText);

        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text.substring(0, 500));
            alert('Server returned non-JSON response. Status: ' + response.status + '\nCheck console for details.');
            return;
        }

        const data = await response.json();
        console.log('Response data:', data);

        if (response.ok) {
            console.log('User saved successfully');
            closeUserModal();
            loadSellers();
        } else {
            console.error('Failed to save user:', data);
            alert(data.message || data.error || JSON.stringify(data.errors || 'Failed to save user'));
        }
    } catch (e) {
        console.error('Connection error:', e);
        alert('Connection error: ' + e.message);
    }
});

async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this seller?')) return;

    try {
        const response = await fetch(`${API_BASE}/users/${id}`, {
            method: 'DELETE',
            headers: getAuthHeaders()
        });

        if (response.ok) {
            loadSellers();
        } else {
            alert('Delete failed');
        }
    } catch (e) {
        alert('Connection error');
    }
}

function editUser(id) {
    openUserModal(id);
}

async function deleteTeam(id) {
    if (!confirm('Are you sure you want to delete this team? All members will be unassigned.')) return;

    try {
        const response = await fetch(`${API_BASE}/teams/${id}`, {
            method: 'DELETE',
            headers: getAuthHeaders()
        });

        if (response.ok) {
            loadTeams();
        } else {
            const data = await response.json();
            alert(data.error || 'Failed to delete team');
        }
    } catch (e) {
        alert('Connection error: ' + e.message);
    }
}

async function editTeamLeader(teamId, currentLeaderEmail) {
    try {
        // Fetch all sellers
        const response = await fetch(`${API_BASE}/users`, {
            headers: getAuthHeaders()
        });

        if (!response.ok) {
            alert('Failed to load sellers');
            return;
        }

        const sellers = await response.json();

        // Create selection prompt
        let message = `Current Leader: ${currentLeaderEmail}\n\nSelect new leader:\n\n`;
        sellers.forEach((seller, index) => {
            message += `${index + 1}. ${seller.name} (${seller.email})\n`;
        });
        message += '\nEnter the number of the new leader:';

        const selection = prompt(message);
        if (!selection) return;

        const index = parseInt(selection) - 1;
        if (index < 0 || index >= sellers.length) {
            alert('Invalid selection');
            return;
        }

        const newLeaderEmail = sellers[index].email;

        // Update team leader
        await updateTeamLeader(teamId, newLeaderEmail);

    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function updateTeamLeader(teamId, newLeaderEmail) {
    try {
        const response = await fetch(`${API_BASE}/teams/${teamId}`, {
            method: 'PUT',
            headers: getAuthHeaders(),
            body: JSON.stringify({ leader_email: newLeaderEmail })
        });

        const data = await response.json();

        if (response.ok) {
            alert('Team leader updated successfully!');
            loadTeams();
            loadSellers(); // Refresh sellers to update badges
        } else {
            alert(data.error || 'Failed to update team leader');
        }
    } catch (e) {
        alert('Connection error: ' + e.message);
    }
}

// ===== TEAM PRODUCT SHEET SYNC =====

async function updateSheetUrl(teamId, currentUrl = '') {
    const newUrl = prompt('Enter Google Sheet URL for Product Sync:', currentUrl);
    if (newUrl === null) return; // Cancelled

    try {
        const res = await fetch(`${API_BASE}/teams/${teamId}`, {
            method: 'PUT',
            headers: getAuthHeaders(),
            body: JSON.stringify({ product_sheet_url: newUrl })
        });

        if (res.ok) {
            alert('Product Sheet URL updated!');
            loadTeams();
        } else {
            alert('Failed to update URL');
        }
    } catch (e) {
        alert('Connection Error');
    }
}

async function syncProducts(teamId) {
    if (!confirm('Start syncing products from the configured Google Sheet? This might take a moment.')) return;

    try {
        const res = await fetch(`${API_BASE}/teams/${teamId}/sync-products`, {
            method: 'POST',
            headers: getAuthHeaders()
        });
        const data = await res.json();

        if (data.success) {
            alert(`Sync Completed!\nUpdated: ${data.updated}\nCreated: ${data.created}`);
            loadProducts(); // Refresh product list
        } else {
            alert('Sync Failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Sync Request Failed');
    }
}

// ─── Product Page: Google Sheet Sync ───

async function initProductGSheetConfig() {
    try {
        if (!window.allTeams) {
            window.allTeams = [];
            // Try admin endpoint
            try {
                const r1 = await fetch(`${API_BASE}/teams`, { headers: getAuthHeaders() });
                if (r1.ok) {
                    const d = await r1.json();
                    if (Array.isArray(d) && d.length) window.allTeams = d;
                }
            } catch(e) { console.log('teams list failed', e); }
            // Fallback: own team
            if (!window.allTeams.length) {
                try {
                    const r2 = await fetch(`${API_BASE}/teams/my`, { headers: getAuthHeaders() });
                    if (r2.ok) {
                        const t = await r2.json();
                        if (t && t.id) window.allTeams = [t];
                    }
                } catch(e) { console.log('teams/my failed', e); }
            }
            console.log('allTeams loaded:', window.allTeams.length, window.allTeams.map(t => t.name));
        }
        const teams = window.allTeams;
        const selectEl = document.getElementById('product-team-select');
        const statusEl = document.getElementById('product-gsheet-status');
        const syncBtn = document.getElementById('btn-sync-gsheet');

        if (!teams.length) {
            if (statusEl) statusEl.textContent = 'No team found';
            return;
        }

        if (selectEl) {
            selectEl.innerHTML = teams.map(t => `<option value="${t.id}">${t.name}${t.product_sheet_url ? ' (Sheet configured)' : ''}</option>`).join('');
            const sheetTeam = teams.find(t => t.product_sheet_url) || teams[0];
            selectEl.value = sheetTeam.id;
            selectEl.onchange = () => updateGSheetUI();
            if (teams.length === 1) selectEl.style.display = 'none';
        }

        updateGSheetUI();
    } catch (e) {
        console.error('initProductGSheetConfig error:', e);
    }
}

function updateGSheetUI() {
    const teams = window.allTeams || [];
    const selectEl = document.getElementById('product-team-select');
    const urlInput = document.getElementById('product-gsheet-url');
    const statusEl = document.getElementById('product-gsheet-status');
    const syncBtn = document.getElementById('btn-sync-gsheet');

    const teamId = parseInt(selectEl?.value);
    const team = teams.find(t => t.id === teamId);
    window.currentTeamData = team;

    if (!team) return;
    if (urlInput) urlInput.value = team.product_sheet_url || '';
    if (team.product_sheet_url) {
        statusEl.innerHTML = '<span class="text-emerald-600 font-bold"><i class="fas fa-check-circle mr-1"></i>Configured</span>';
        syncBtn.classList.remove('hidden');
    } else {
        statusEl.textContent = 'Paste your Google Sheet URL and click Save';
        syncBtn.classList.add('hidden');
    }
}

async function saveProductSheetUrl() {
    const url = document.getElementById('product-gsheet-url').value.trim();
    const team = window.currentTeamData;
    if (!team) {
        alert('No team found. Please create a team first in the Teams tab.');
        return;
    }
    try {
        const res = await fetch(`${API_BASE}/teams/${team.id}`, {
            method: 'PUT',
            headers: getAuthHeaders(),
            body: JSON.stringify({ product_sheet_url: url })
        });
        const data = await res.json();
        if (data.id || data.success) {
            window.currentTeamData.product_sheet_url = url;
            initProductGSheetConfig();
            alert('Google Sheet URL saved!');
        } else {
            alert('Save failed: ' + (data.error || 'Unknown error'));
        }
    } catch (e) {
        alert('Save failed: ' + e.message);
    }
}

async function syncProductsFromProducts() {
    const team = window.currentTeamData;
    if (!team || !team.product_sheet_url) {
        alert('Please configure a Google Sheet URL first.');
        return;
    }
    syncProducts(team.id);
}

async function viewOrder(id) {
    try {
        const response = await fetch(`${API_BASE}/orders/${id}`, { headers: getAuthHeaders() });
        const order = await response.json();

        if (order && (order.id || order.orderID)) {
            window.currentViewingOrder = order; // Cache for actions
            renderOrderDetail(order);
            switchTab('order-detail');
        } else {
            alert('Order not found');
        }
    } catch (e) {
        console.error(e);
        alert('Failed to load order details');
    }
}

function renderOrderDetail(order) {
    // Basic Info
    document.getElementById('detail-order-id').textContent = `#${order.orderID || order.order_id || order.id}`;
    document.getElementById('detail-partner-id').textContent = (order.storeId || order.PartnerOrderID) ? `(Store ID: ${order.storeId || order.PartnerOrderID})` : '(Store ID: —)';

    const createdDate = order.order_date || order.created_at ? new Date(order.order_date || order.created_at).toLocaleString() : '—';
    const expectDate = order.ExpectDate ? new Date(order.ExpectDate).toLocaleDateString() : '—';
    document.getElementById('detail-order-dates').textContent = `Created: ${createdDate} | Expect: ${expectDate}`;

    // Status Badges
    const status = (order.Status || order.status || 'PENDING').toUpperCase();
    const payment = (order.PaymentStatus || order.payment || 'SUCCESS').toUpperCase();

    const statusEl = document.getElementById('detail-status');
    statusEl.className = `stage-badge stage-${status}`;
    statusEl.textContent = status;

    const paymentEl = document.getElementById('detail-payment');
    paymentEl.className = `payment-badge payment-${payment}`;
    paymentEl.textContent = payment;

    document.getElementById('detail-source').textContent = order.Platform || order.platform || 'Walmart';

    // Shipping Info
    const cust = order.customer_details || order.customer || {};
    const addr = cust.address || {};
    const shippingHtml = `
        <div class="info-item">
            <span class="info-label">Customer's name</span>
            <span class="info-value">${cust.name || (cust.firstName ? `${cust.firstName} ${cust.lastName || ''}` : 'Guest')}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Email</span>
            <span class="info-value truncate" title="${cust.email || ''}">${cust.email || '—'}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Address 1</span>
            <span class="info-value">${cust.address1 || addr.line1 || '—'}</span>
        </div>
        <div class="info-item">
            <span class="info-label">City</span>
            <span class="info-value">${cust.city || addr.city || '—'}</span>
        </div>
        <div class="info-item">
            <span class="info-label">State</span>
            <span class="info-value">${cust.state || addr.state || '—'}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Zip code</span>
            <span class="info-value">${cust.zip || addr.zip || '—'}</span>
        </div>
    `;
    document.getElementById('detail-shipping-info').innerHTML = shippingHtml;

    // Product List
    const lineItems = order.line_items || [order]; // Fallback if no line_items
    const tbody = document.getElementById('detail-products-tbody');
    tbody.innerHTML = lineItems.map((item, index) => {
        // Handle raw model vs mapped object structure
        const details = item.product_details || {};
        const prodName = item.ProductName || details.name || item.name || 'Unnamed Product';
        const img = item.MOCKUPURL || details.mockup_url || 'https://via.placeholder.com/80';
        const sku = item.SKU || details.sku || item.sku || 'N/A';
        const qty = item.Quantity || details.quantity || item.quantity || 1;
        const price = item.price || details.price || 0;
        const total = (price * qty).toFixed(2);

        return `
            <tr class="align-middle">
                <td class="text-slate-400 font-medium">${index + 1}</td>
                <td>
                    <div class="flex items-center gap-3">
                        <img src="${img}" class="table-thumbnail">
                        <div class="max-w-[180px]">
                            <p class="text-sm font-semibold text-slate-900 truncate" title="${prodName}">${prodName}</p>
                            <p class="text-xs text-slate-400">SKU: ${sku}</p>
                        </div>
                    </div>
                </td>
                <td id="design-front-td-${index}"><span class="text-xs text-slate-400">Loading...</span></td>
                <td id="design-back-td-${index}"><span class="text-xs text-slate-400">—</span></td>
                <td class="font-bold text-slate-900">${qty}</td>
                <td class="text-right font-bold text-slate-900">$${total}</td>
            </tr>
        `;
    }).join('');

    // Fetch design mapping and fill thumbnails + auto-fill inputs
    const orderSku = getOrderSku(order);
    if (orderSku) {
        fetchDesignMapping(orderSku).then(mapping => {
            if (!mapping) {
                // No mapping: show placeholder
                lineItems.forEach((_, i) => {
                    document.getElementById(`design-front-td-${i}`).innerHTML = '<span class="text-xs text-slate-400">—</span>';
                });
                return;
            }
            // Show thumbnails in product table
            lineItems.forEach((_, i) => {
                const frontTd = document.getElementById(`design-front-td-${i}`);
                const backTd = document.getElementById(`design-back-td-${i}`);
                if (frontTd && mapping.design_url) {
                    frontTd.innerHTML = `<a href="${mapping.design_url}" target="_blank"><img src="${mapping.design_url}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;cursor:pointer;" onerror="this.style.display='none';this.parentElement.innerHTML='<span class=\\'text-xs text-slate-400\\'>File</span>'"></a>`;
                } else if (frontTd) {
                    frontTd.innerHTML = '<span class="text-xs text-slate-400">—</span>';
                }
                if (backTd && mapping.design_url_2) {
                    backTd.innerHTML = `<a href="${mapping.design_url_2}" target="_blank"><img src="${mapping.design_url_2}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;cursor:pointer;" onerror="this.style.display='none';this.parentElement.innerHTML='<span class=\\'text-xs text-slate-400\\'>File</span>'"></a>`;
                }
            });
            // Auto-fill design/mockup URL inputs (only if empty)
            const designInput = document.getElementById('detail-design-url');
            const mockupInput = document.getElementById('detail-mockup-url');
            if (designInput && !designInput.value && mapping.design_url) {
                designInput.value = mapping.design_url;
            }
            if (mockupInput && !mockupInput.value && mapping.mockup_url) {
                mockupInput.value = mapping.mockup_url;
            }
        });
    }

    // Totals
    const totalVal = order.Total || order.total || '0.00';
    document.getElementById('detail-subtotal').textContent = `$${totalVal}`;
    document.getElementById('detail-total').textContent = `$${totalVal}`;

    // Trackings
    const trackBox = document.getElementById('detail-trackings');
    const trackingNum = order.tracking_number || (order.tracking_info && order.tracking_info.number);
    if (trackingNum) {
        trackBox.innerHTML = `
            <div class="p-3 bg-emerald-50 text-emerald-700 rounded-lg flex items-center gap-3">
                <i class="fas fa-truck text-lg"></i>
                <div>
                    <p class="font-bold">#${trackingNum}</p>
                    <p class="text-xs">${order.carrier || (order.tracking_info && order.tracking_info.carrier) || 'Standard Shipping'}</p>
                </div>
            </div>
        `;
    } else {
        trackBox.innerHTML = '<div class="text-slate-400 italic">No tracking info available.</div>';
    }

    // Default Supplier
    document.getElementById('detail-supplier-select').value = 'Flashship';
    onDetailSupplierChange();
}

// Store all variants for filtering
let allDetailVariants = [];

async function onDetailSupplierChange() {
    const supplier = document.getElementById('detail-supplier-select').value;
    const variantSelect = document.getElementById('detail-variant-select');
    const searchInput = document.getElementById('detail-product-search');

    // Reset
    searchInput.value = '';
    allDetailVariants = [];
    variantSelect.innerHTML = '<option>Loading variants...</option>';

    try {
        let url = '';
        if (supplier === 'Flashship') url = `${API_BASE}/fulfillment/flashship/variants`;
        else if (supplier === 'FJPOD') url = `${API_BASE}/fulfillment/fjpod/skus`;
        else if (supplier === 'Printway') url = `${API_BASE}/fulfillment/printway/products`;

        console.log('[Variants] Fetching from:', url);
        const res = await fetch(url, { headers: getAuthHeaders() });
        const json = await res.json();
        console.log('[Variants] Response status:', res.status, 'success:', json.success, 'dataType:', typeof json.data, 'isArray:', Array.isArray(json.data));

        // API returns { success: true, data: [...] } for Flashship variants
        const items = Array.isArray(json) ? json : (json.data || []);
        console.log('[Variants] Loaded:', items.length, 'items. First:', items[0]);

        if (items && items.length > 0) {
            allDetailVariants = items;
            // Only show first 200 in dropdown initially (performance)
            const display = items.slice(0, 200);
            variantSelect.innerHTML = `<option value="">-- ${items.length} variants loaded, type to filter --</option>` +
                display.map(v => renderVariantOption(v)).join('');
        } else {
            variantSelect.innerHTML = '<option value="">No variants found</option>';
        }
    } catch (e) {
        console.error('[Variants] Load error:', e);
        allDetailVariants = [];
        variantSelect.innerHTML = `<option value="">Error: ${e.message}</option>`;
    }
}

function renderVariantOption(v) {
    const id = v.variant_id || v.id || v.sku;
    const label = v.variant_id
        ? `${v.product_type || ''} ${v.style || ''} - ${v.color || ''} / ${v.size || ''} (${v.brand || ''})`
        : (v.title || v.name || v.sku);
    return `<option value="${id}">${label}</option>`;
}

function filterDetailVariants() {
    const searchTerm = document.getElementById('detail-product-search').value.trim().toLowerCase();
    const variantSelect = document.getElementById('detail-variant-select');

    console.log('[Filter] searchTerm:', searchTerm, 'allDetailVariants.length:', allDetailVariants.length);

    if (allDetailVariants.length === 0) {
        variantSelect.innerHTML = '<option value="">Variants not loaded yet - please wait</option>';
        // Retry loading
        onDetailSupplierChange();
        return;
    }

    if (!searchTerm) {
        const display = allDetailVariants.slice(0, 200);
        variantSelect.innerHTML = `<option value="">-- ${allDetailVariants.length} variants, type to filter --</option>` +
            display.map(v => renderVariantOption(v)).join('');
        return;
    }

    const filtered = allDetailVariants.filter(v => {
        const text = `${v.product_type || ''} ${v.style || ''} ${v.color || ''} ${v.size || ''} ${v.brand || ''} ${v.title || ''} ${v.name || ''} ${v.sku || ''}`.toLowerCase();
        return text.includes(searchTerm);
    });

    console.log('[Filter] Found:', filtered.length, 'matches');

    if (filtered.length > 0) {
        variantSelect.innerHTML = `<option value="">-- ${filtered.length} matches --</option>` +
            filtered.map(v => renderVariantOption(v)).join('');
    } else {
        variantSelect.innerHTML = '<option value="">No matching variants for "' + searchTerm + '"</option>';
    }
}

// ─── Design Mapping helpers ───
async function fetchDesignMapping(sku) {
    if (!sku) return null;
    try {
        const res = await fetch(`${API_BASE}/design-mappings/${encodeURIComponent(sku)}`, { headers: getAuthHeaders() });
        if (!res.ok) return null;
        const json = await res.json();
        return json.success ? json.data : null;
    } catch { return null; }
}

async function saveDesignMapping(sku, fields) {
    if (!sku) return;
    try {
        await fetch(`${API_BASE}/design-mappings`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify({ base_sku: sku, ...fields })
        });
    } catch (e) { console.error('Save design mapping error:', e); }
}

function getOrderSku(order) {
    const details = order.product_details || {};
    return details.sku || order.SKU || order.sku || '';
}

function uploadDesignFile(type) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*,application/pdf,.svg,.dst,.zip';

    input.onchange = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file size (50MB max)
        if (file.size > 50 * 1024 * 1024) {
            alert('File too large. Max 50MB.');
            return;
        }

        const folder = type === 'mockup' ? 'mockups' : 'designs';
        const urlInput = document.getElementById(`detail-${type}-url`);
        const uploadBtn = urlInput.parentElement.querySelector('button');
        const originalBtnHtml = uploadBtn.innerHTML;

        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folder', folder);

            const token = getToken();
            const res = await fetch(`${API_BASE}/upload`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const result = await res.json();

            if (result.success && result.url) {
                urlInput.value = result.url;
                // Auto-save design mapping by SKU
                const sku = getOrderSku(window.currentViewingOrder || {});
                if (sku) {
                    const field = type === 'mockup' ? 'mockup_url' : 'design_url';
                    saveDesignMapping(sku, { [field]: result.url });
                }
                alert(`Upload OK: ${file.name}`);
            } else {
                alert('Upload failed: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            console.error('Upload error:', err);
            alert('Upload failed: ' + err.message);
        } finally {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = originalBtnHtml;
        }
    };

    input.click();
}

async function handleSendToProduce() {
    const order = window.currentViewingOrder;
    if (!order) return;

    const supplier = document.getElementById('detail-supplier-select').value;
    const variant = document.getElementById('detail-variant-select').value;
    const designUrl = document.getElementById('detail-design-url').value;
    const mockupUrl = document.getElementById('detail-mockup-url').value;
    const printLocation = document.getElementById('detail-print-location').value;
    const printType = document.getElementById('detail-print-type').value;

    // Validate required fields
    if (!designUrl || !mockupUrl || !printLocation) {
        alert('Please fill in all required fields: Design URL, Mockup URL, and Print Location');
        return;
    }

    if (supplier === 'Flashship' && !variant) {
        alert('Please select a product variant for Flashship');
        return;
    }

    // Collect special print areas
    const specialPrintAreas = {
        leftSleeve: document.getElementById('detail-left-sleeve').checked,
        rightSleeve: document.getElementById('detail-right-sleeve').checked,
        neckLabel: document.getElementById('detail-neck-label').checked,
        neck: document.getElementById('detail-neck').checked,
        hood: document.getElementById('detail-hood').checked,
        pocket: document.getElementById('detail-pocket').checked
    };

    if (confirm(`Send order ${order.orderID || order.id} to produce via ${supplier}?`)) {
        try {
            const body = {
                supplier,
                variant_id: variant,
                design_url: designUrl,
                mockup_url: mockupUrl,
                print_location: printLocation,
                print_type: printType,
                special_print_areas: specialPrintAreas
            };

            const res = await fetch(`${API_BASE}/orders/${order.id}/fulfill`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify(body)
            });

            if (res.ok) {
                // Save design mapping for future orders with same SKU
                const sku = getOrderSku(order);
                if (sku && (designUrl || mockupUrl)) {
                    const mapFields = {};
                    if (designUrl) mapFields.design_url = designUrl;
                    if (mockupUrl) mapFields.mockup_url = mockupUrl;
                    saveDesignMapping(sku, mapFields);
                }
                alert('Sent to production successfully!');
                viewOrder(order.id); // Refresh
            } else {
                const error = await res.json();
                alert(`Fulfillment failed: ${error.message || 'Unknown error'}`);
            }
        } catch (e) {
            alert('Connection error: ' + e.message);
        }
    }
}

function switchDetailTab(tab) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
    document.getElementById(`detail-${tab}-content`).classList.remove('hidden');

    document.querySelectorAll('#detail-tabs button').forEach(b => {
        b.classList.remove('border-vibrant-primary', 'text-white');
        b.classList.add('border-transparent', 'text-slate-400');
    });

    document.getElementById(`tab-${tab}`).classList.add('border-vibrant-primary', 'text-white');
    document.getElementById(`tab-${tab}`).classList.remove('border-transparent', 'text-slate-400');

    if (tab === 'notes') {
        loadOrderNotes(window.currentOrderDetailId);
    }
}

async function loadOrderNotes(orderId) {
    const list = document.getElementById('notes-list');
    list.innerHTML = '<p class="text-xs text-slate-500">Loading notes...</p>';
    try {
        const res = await fetch(`${API_BASE}/orders/${orderId}/notes`, { headers: getAuthHeaders() });
        const data = await res.json();
        if (data.success && data.data) {
            list.innerHTML = data.data.map(note => `
                <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/50 relative group">
                    <div class="flex justify-between items-start mb-1">
                        <span class="text-xs font-bold text-vibrant-primary">${note.user?.name || 'Unknown'}</span>
                        <span class="text-[10px] text-slate-500">${new Date(note.created_at).toLocaleString()}</span>
                    </div>
                    <p class="text-sm text-slate-300">${note.content}</p>
                    <button onclick="deleteNote(${note.id})" class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 text-slate-500 hover:text-red-400 transition-all">
                        <i class="fas fa-trash-alt text-[10px]"></i>
                    </button>
                </div>
            `).join('') || '<p class="text-xs text-slate-500 italic">No notes added yet.</p>';
        }
    } catch (e) { list.innerHTML = 'Error loading notes.'; }
}

async function handleAddNote() {
    const input = document.getElementById('note-input');
    const content = input.value.trim();
    if (!content) return;

    try {
        const res = await fetch(`${API_BASE}/orders/${window.currentOrderDetailId}/notes`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify({ content })
        });
        if (res.ok) {
            input.value = '';
            loadOrderNotes(window.currentOrderDetailId);
        }
    } catch (e) { alert('Failed to add note'); }
}

async function deleteNote(id) {
    if (!confirm('Delete this note?')) return;
    try {
        const res = await fetch(`${API_BASE}/notes/${id}`, {
            method: 'DELETE',
            headers: getAuthHeaders()
        });
        if (res.ok) loadOrderNotes(window.currentOrderDetailId);
    } catch (e) { alert('Failed to delete note'); }
}

// --- FULFILLMENT SOPHISTICATION ---
let currentFulfillOrderId = null;
let currentSelectedSupplier = null;
let selectedFjpodSku = null;

function openFulfillmentModal(orderId) {
    currentFulfillOrderId = orderId;
    currentSelectedSupplier = null;
    selectedFjpodSku = null;

    // Reset UI
    document.querySelectorAll('.sup-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('fjpod-sku-section').classList.add('hidden');
    document.getElementById('fjpod-sku-list').innerHTML = '<p class="text-xs text-slate-500 italic text-center">Enter code to see SKUs</p>';
    document.getElementById('fjpod-product-code').value = '';

    document.getElementById('fulfillment-modal').classList.remove('hidden');
}

function closeFulfillmentModal() {
    document.getElementById('fulfillment-modal').classList.add('hidden');
}

function selectSupplier(supplier) {
    currentSelectedSupplier = supplier;
    document.querySelectorAll('.sup-btn').forEach(btn => {
        btn.classList.toggle('active', btn.id === `btn-sup-${supplier}`);
    });

    const fjpodSection = document.getElementById('fjpod-sku-section');
    if (supplier === 'FJPOD') {
        fjpodSection.classList.remove('hidden');
    } else {
        fjpodSection.classList.add('hidden');
    }
}

async function searchFJPODSKUs() {
    const code = document.getElementById('fjpod-product-code').value.trim();
    if (!code) return;

    const btn = document.getElementById('btn-fjpod-search');
    const list = document.getElementById('fjpod-sku-list');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    list.innerHTML = '<div class="text-center py-4"><i class="fas fa-circle-notch fa-spin text-slate-600"></i></div>';

    try {
        const response = await fetch(`${API_BASE}/fulfillment/fjpod/skus?productCode=${code}`, {
            headers: getAuthHeaders()
        });
        const result = await response.json();

        if (result.success) {
            if (result.data.length === 0) {
                list.innerHTML = '<p class="text-xs text-red-400 text-center py-4">No SKUs found for this code.</p>';
            } else {
                list.innerHTML = result.data.map(sku => `
                    <div class="sku-item p-3 rounded-lg bg-slate-800/50 border border-slate-700 hover:border-vibrant-primary transition-all" onclick="selectFjpodSku(this, '${sku.sku}')">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-mono text-white">${sku.sku}</span>
                            <span class="text-[10px] text-slate-400">${sku.color} / ${sku.size}</span>
                        </div>
                    </div>
                `).join('');
            }
        } else {
            list.innerHTML = `<p class="text-xs text-red-500 text-center py-4">${result.error || 'Failed to fetch SKUs'}</p>`;
        }
    } catch (error) {
        list.innerHTML = '<p class="text-xs text-red-500 text-center py-4">Error fetching SKUs</p>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Search';
    }
}

function selectFjpodSku(el, sku) {
    selectedFjpodSku = sku;
    document.querySelectorAll('.sku-item').forEach(item => item.classList.remove('selected'));
    el.classList.add('selected');
}

async function submitFulfillment() {
    if (!currentSelectedSupplier) {
        alert('Please select a supplier');
        return;
    }

    if (currentSelectedSupplier === 'FJPOD' && !selectedFjpodSku) {
        alert('Please select an FJPOD SKU');
        return;
    }

    const btn = document.getElementById('btn-fulfill-submit');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Sending...';

    const payload = {
        supplier: currentSelectedSupplier,
        print_tech: document.getElementById('fulfill-print-tech').value,
        print_size_front: document.getElementById('fulfill-print-size').value
    };

    if (currentSelectedSupplier === 'FJPOD') {
        payload.sku = selectedFjpodSku;
    }

    try {
        const response = await fetch(`${API_BASE}/orders/${currentFulfillOrderId}/fulfill`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Success', `Order sent to ${currentSelectedSupplier}`, 'success');
            closeFulfillmentModal();
            closeOrderDetailModal();
            loadOrders(); // Refresh list
        } else {
            alert(`Error: ${result.error || result.message}`);
        }
    } catch (error) {
        alert('Failed to connect to server');
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
}

async function fulfillOrder(orderId) {
    openFulfillmentModal(orderId);
}

function closeOrderDetailModal() {
    document.getElementById('order-detail-modal').classList.add('hidden');
}

// --- FULFILLMENT TAB LOGIC ---

async function loadFulfillment() {
    const tbody = document.getElementById('fulfillment-tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading fulfillment data...</td></tr>';

    try {
        const response = await fetch(`${API_BASE}/orders?limit=100&status=PENDING,DESIGNING,PRODUCTION,SHIPPED`, {
            headers: getAuthHeaders()
        });
        const data = await response.json();
        const orders = data.data || [];

        // Update Stats
        const stats = {
            pending: orders.filter(o => o.status === 'pending' || o.status === 'PENDING').length,
            production: orders.filter(o => o.status === 'production' || o.status === 'PRODUCTION').length,
            shipped: orders.filter(o => (o.status === 'shipped' || o.status === 'SHIPPED') && !o.tracking_pushed).length
        };
        document.getElementById('fulfill-stat-pending').textContent = stats.pending;
        document.getElementById('fulfill-stat-production').textContent = stats.production;
        document.getElementById('fulfill-stat-shipped').textContent = stats.shipped;

        if (orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-slate-500">No orders found for fulfillment.</td></tr>';
            return;
        }

        tbody.innerHTML = orders.map(order => {
            const f = order.fulfillment || {};
            const t = order.tracking_info || {};
            const status = (order.status || 'PENDING').toUpperCase();

            return `
                <tr>
                    <td class="font-mono font-bold text-indigo-600">#${order.order_id}</td>
                    <td class="text-slate-900 font-medium">${f.provider || f.supplier || '---'}</td>
                    <td class="text-slate-500 font-mono text-xs">${f.supplierOrderId || '---'}</td>
                    <td>
                        ${t.number ? `
                            <div class="flex flex-col">
                                <span class="text-slate-900 font-bold text-xs">${t.number}</span>
                                <span class="text-[10px] text-slate-400 uppercase font-bold">${t.carrier || ''}</span>
                            </div>
                        ` : '<span class="text-slate-400 text-xs italic">No Tracking</span>'}
                    </td>
                    <td>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border
                            ${status === 'PENDING' ? 'bg-amber-100 text-amber-700 border-amber-200' :
                    status === 'PRODUCTION' ? 'bg-indigo-100 text-indigo-700 border-indigo-200' :
                        status === 'SHIPPED' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' :
                            'bg-slate-100 text-slate-600 border-slate-200'}">
                            ${status}
                        </span>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-2">
                            <button onclick="viewOrder('${order.order_id}')" class="p-2 text-slate-400 hover:text-indigo-600 transition-colors" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${status === 'PENDING' ? `
                                <button onclick="fulfillOrder('${order.order_id}')" class="p-2 text-emerald-500 hover:text-emerald-600 transition-colors" title="Fulfill Now">
                                    <i class="fas fa-box-open"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

    } catch (e) {
        console.error(e);
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-red-500">Failed to load fulfillment data.</td></tr>';
    }
}

function switchFulfillTab(tab) {
    const localPanel = document.getElementById('fulfill-local-panel');
    const fsPanel = document.getElementById('fulfill-flashship-panel');
    const btnLocal = document.getElementById('ftab-local');
    const btnFs = document.getElementById('ftab-flashship');

    if (tab === 'flashship') {
        localPanel.classList.add('hidden');
        fsPanel.classList.remove('hidden');
        btnLocal.className = 'px-4 py-2 rounded-lg text-sm font-bold bg-slate-100 text-slate-600 hover:bg-slate-200';
        btnFs.className = 'px-4 py-2 rounded-lg text-sm font-bold bg-indigo-600 text-white';
        loadFlashshipOrders();
    } else {
        fsPanel.classList.add('hidden');
        localPanel.classList.remove('hidden');
        btnFs.className = 'px-4 py-2 rounded-lg text-sm font-bold bg-slate-100 text-slate-600 hover:bg-slate-200';
        btnLocal.className = 'px-4 py-2 rounded-lg text-sm font-bold bg-indigo-600 text-white';
    }
}

async function loadFlashshipOrders() {
    const tbody = document.getElementById('flashship-orders-tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-10 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i> Loading FlashShip orders...</td></tr>';

    try {
        const res = await fetch(`${API_BASE}/fulfillment/flashship/orders`, { headers: getAuthHeaders() });
        const result = await res.json();
        const orders = result.data || [];

        if (!orders.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-10 text-center text-slate-500">No FlashShip fulfilled orders found. Use "Lookup" above to search by FlashShip Order ID, or fulfill orders via FlashShip first.</td></tr>';
            return;
        }

        tbody.innerHTML = orders.map(o => {
            const f = o.fulfillment || {};
            const t = o.tracking_info || {};
            const status = (f.status || o.status || 'PENDING').toUpperCase();
            const statusClass = status === 'SHIPPED' || status === 'DELIVERED' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' :
                status === 'PRODUCTION' || status === 'PENDING' ? 'bg-indigo-100 text-indigo-700 border-indigo-200' :
                status === 'CANCELLED' ? 'bg-rose-100 text-rose-700 border-rose-200' :
                'bg-slate-100 text-slate-600 border-slate-200';
            const tracking = f.trackingNumber || t.number || '';
            const carrier = f.carrier || t.carrier || '';
            const details = o.product_details || {};
            const created = f.fulfilledAt ? new Date(f.fulfilledAt).toLocaleDateString() : (o.order_date ? new Date(o.order_date).toLocaleDateString() : '—');
            const fsId = f.supplierOrderId || '—';

            return `<tr>
                <td class="font-mono font-bold text-indigo-600">${fsId}</td>
                <td class="font-mono text-xs text-slate-700">${o.order_id || '—'}</td>
                <td class="text-sm text-slate-600 max-w-[200px] truncate" title="${details.name || ''}">${details.name || '—'}</td>
                <td>${tracking ? `<div class="flex flex-col"><span class="text-xs font-bold text-slate-900">${tracking}</span><span class="text-[10px] text-slate-400 uppercase font-bold">${carrier}</span></div>` : '<span class="text-xs text-slate-400 italic">No Tracking</span>'}</td>
                <td><span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border ${statusClass}">${status}</span></td>
                <td class="text-xs text-slate-500">${created}</td>
                <td class="text-right">
                    ${fsId !== '—' ? `<button onclick="lookupFlashshipOrder('${fsId}')" class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded text-xs font-bold hover:bg-indigo-200" title="Refresh from FlashShip"><i class="fas fa-sync-alt"></i></button>` : ''}
                </td>
            </tr>`;
        }).join('');
    } catch (e) {
        console.error('FlashShip orders error:', e);
        tbody.innerHTML = '<tr><td colspan="7" class="px-6 py-10 text-center text-red-500">Failed to load FlashShip orders.</td></tr>';
    }
}

async function lookupFlashshipOrder(orderId) {
    if (!orderId) {
        orderId = document.getElementById('fs-lookup-id')?.value?.trim();
    }
    if (!orderId) { alert('Please enter a FlashShip Order ID'); return; }

    const resultDiv = document.getElementById('fs-lookup-result');
    resultDiv.classList.remove('hidden');
    resultDiv.innerHTML = '<div class="p-3 bg-slate-50 rounded-lg text-sm text-slate-500"><i class="fas fa-spinner fa-spin mr-1"></i> Looking up order ' + orderId + '...</div>';

    try {
        const res = await fetch(`${API_BASE}/fulfillment/flashship/lookup`, {
            method: 'POST',
            headers: { ...getAuthHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        });
        const data = await res.json();

        if (data.success) {
            const raw = data.raw || {};
            const tracking = data.tracking_number || raw.tracking_number || '';
            const carrier = data.carrier || raw.carrier || '';
            const trackingUrl = data.tracking_url || raw.tracking_url || '';
            const status = data.status || raw.status || 'unknown';
            const shipped = data.shipped;

            let html = `<div class="p-4 bg-white border border-slate-200 rounded-lg space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-lg text-indigo-600">${orderId}</span>
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase ${shipped ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">${status}</span>
                </div>`;

            if (tracking) {
                html += `<div class="flex items-center gap-2">
                    <span class="text-sm text-slate-500">Tracking:</span>
                    <span class="font-mono font-bold text-sm">${tracking}</span>
                    <span class="text-xs text-slate-400 uppercase">${carrier}</span>
                    ${trackingUrl ? `<a href="${trackingUrl}" target="_blank" class="text-indigo-600 text-xs hover:underline"><i class="fas fa-external-link-alt"></i></a>` : ''}
                </div>`;
            } else {
                html += `<div class="text-sm text-slate-400 italic">No tracking number yet</div>`;
            }

            // Show raw data details
            if (raw.total_fee != null) {
                html += `<div class="text-xs text-slate-500">Cost: $${(raw.total_fee || 0).toFixed(2)} | Shipping: $${(raw.shipping_fee || raw.shipping_cost || 0).toFixed(2)}</div>`;
            }
            if (raw.created_at) {
                html += `<div class="text-xs text-slate-400">Created: ${new Date(raw.created_at).toLocaleString()}</div>`;
            }

            html += `</div>`;
            resultDiv.innerHTML = html;
        } else {
            resultDiv.innerHTML = `<div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><i class="fas fa-exclamation-circle mr-1"></i> ${data.error || 'Order not found'}</div>`;
        }
    } catch (e) {
        console.error('Lookup error:', e);
        resultDiv.innerHTML = `<div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><i class="fas fa-exclamation-circle mr-1"></i> Lookup failed: ${e.message}</div>`;
    }
}

async function syncAllFlashshipTracking() {
    if (!confirm('Sync tracking for all FlashShip orders in PRODUCTION status?')) return;
    try {
        const res = await fetch(`${API_BASE}/fulfillment/flashship/sync-tracking`, {
            method: 'POST',
            headers: getAuthHeaders()
        });
        const data = await res.json();
        if (data.success) {
            alert(`Tracking synced! ${data.count} order(s) updated.`);
            loadFlashshipOrders();
            loadFulfillment();
        } else {
            alert('Sync failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Sync error: ' + e.message);
    }
}

async function syncFlashshipTracking() {
    if (!confirm('Sync tracking for all Flashship orders?')) return;
    try {
        const res = await fetch(`${API_BASE}/fulfillment/flashship/sync-tracking`, {
            method: 'POST',
            headers: getAuthHeaders()
        });
        const data = await res.json();
        if (data.success) {
            alert('Flashship tracking sync started!');
            loadFulfillment();
        } else {
            alert('Sync failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) { alert('Connection Error'); }
}

async function syncFJPODTracking() {
    if (!confirm('Sync tracking for all FJPOD orders?')) return;
    try {
        const res = await fetch(`${API_BASE}/fulfillment/fjpod/sync-tracking`, {
            method: 'POST',
            headers: getAuthHeaders()
        });
        const data = await res.json();
        if (data.success) {
            alert('FJPOD tracking sync started!');
            loadFulfillment();
        } else {
            alert('Sync failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) { alert('Connection Error'); }
}

async function syncPrintwayTracking() {
    if (!confirm('Sync tracking for all Printway orders?')) return;
    try {
        const res = await fetch(`${API_BASE}/fulfillment/printway/sync-tracking`, {
            method: 'POST',
            headers: getAuthHeaders()
        });
        const data = await res.json();
        if (data.success) {
            alert('Printway tracking sync started!');
            loadFulfillment();
        } else {
            alert('Sync failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) { alert('Connection Error'); }
}

async function syncWalmartTracking() {
    if (!confirm('Push tracking info to Walmart for all shipped orders?')) return;
    try {
        const res = await fetch(`${API_BASE}/fulfillment/walmart/sync-tracking`, {
            method: 'POST',
            headers: getAuthHeaders()
        });
        const data = await res.json();
        if (data.success) {
            alert('Walmart tracking upload started!');
            loadFulfillment();
        } else {
            alert('Upload failed: ' + (data.message || 'Unknown error'));
        }
    } catch (e) { alert('Connection Error'); }
}

// ===== BULK FULFILLMENT LOGIC =====

let selectedOrderIds = [];

function toggleSelectAllOrders(checkbox) {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    onOrderSelectChange();
}

function onOrderSelectChange() {
    selectedOrderIds = Array.from(document.querySelectorAll('.order-checkbox:checked')).map(cb => cb.value);
    const btn = document.getElementById('btn-bulk-fulfill');
    const countEl = document.getElementById('selected-count');

    if (selectedOrderIds.length > 0) {
        btn.classList.remove('hidden');
        countEl.textContent = selectedOrderIds.length;
    } else {
        btn.classList.add('hidden');
    }
}

async function openBulkFulfillmentModal() {
    if (selectedOrderIds.length === 0) return;

    const modal = document.getElementById('bulk-fulfillment-modal');
    document.getElementById('bulk-item-count').textContent = selectedOrderIds.length;

    // Fetch order details for all selected IDs
    try {
        const promises = selectedOrderIds.map(id => fetch(`${API_BASE}/orders/${id}`, { headers: getAuthHeaders() }).then(r => r.json()));
        const orders = await Promise.all(promises);

        // Render items list
        const list = document.getElementById('bulk-items-list');
        list.innerHTML = orders.map(order => {
            const prod = order.product_details || {};
            return `
                <div class="p-4 bg-slate-800/40 rounded-2xl border border-slate-800 flex gap-4 bulk-item" data-id="${order.id}" data-order-id="${order.order_id}">
                    <img src="${prod.mockup_url || 'https://via.placeholder.com/60'}" class="w-16 h-16 rounded-xl object-cover bg-slate-900 border border-slate-800">
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <div>
                                <h5 class="text-sm font-bold text-white mb-0.5">${prod.name || 'Unnamed Product'}</h5>
                                <p class="text-[10px] text-slate-500 font-mono">#${order.order_id} | ${prod.sku}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-bold text-vibrant-primary">Qty: ${prod.quantity || 1}</span>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-4 gap-2">
                             <input type="text" placeholder="Front Design URL" class="glass-input-sm text-[10px] item-design-front" value="${order.design?.designUrl || ''}">
                             <input type="text" placeholder="Back Design URL" class="glass-input-sm text-[10px] item-design-back" value="${order.design?.backDesignUrl || ''}">
                             <input type="text" placeholder="Left Sleeve URL" class="glass-input-sm text-[10px] item-design-left" value="${order.design?.leftSleeveUrl || ''}">
                             <input type="text" placeholder="Right Sleeve URL" class="glass-input-sm text-[10px] item-design-right" value="${order.design?.rightSleeveUrl || ''}">
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Preview shipping (from first order)
        const first = orders[0];
        const cust = first.customer_details || {};
        const addr = cust.address || {};
        document.getElementById('bulk-shipping-preview').innerHTML = `
            <p class="font-bold text-white">${cust.firstName || ''} ${cust.lastName || ''}</p>
            <p>${addr.line1 || ''} ${addr.line2 || ''}</p>
            <p>${addr.city || ''}, ${addr.state || ''} ${addr.zip || ''}</p>
            <p>${addr.country || 'US'}</p>
        `;

        updateBulkUI();
        modal.classList.remove('hidden');
    } catch (e) {
        alert('Failed to load order details for bulk fulfillment');
        console.error(e);
    }
}

function updateBulkUI() {
    const supplier = document.getElementById('bulk-supplier').value;
    const fsTypeBox = document.getElementById('bulk-flashship-print-type-box');

    if (supplier === 'Flashship') {
        fsTypeBox.classList.remove('hidden');
    } else {
        fsTypeBox.classList.add('hidden');
    }
}

function closeBulkFulfillmentModal() {
    document.getElementById('bulk-fulfillment-modal').classList.add('hidden');
}

async function submitBulkFulfillment() {
    const btn = event.currentTarget;
    const supplier = document.getElementById('bulk-supplier').value;
    const printTech = document.getElementById('bulk-print-tech').value;
    const flashshipType = document.getElementById('bulk-flashship-type').value;

    const items = Array.from(document.querySelectorAll('.bulk-item')).map(div => {
        return {
            id: div.dataset.id,
            order_id: div.dataset.orderId,
            sku: div.querySelector('.font-mono').textContent.split('|')[1].trim(),
            quantity: parseInt(div.querySelector('.text-vibrant-primary').textContent.split(':')[1].trim()),
            designUrl: div.querySelector('.item-design-front').value,
            backDesignUrl: div.querySelector('.item-design-back').value,
            leftSleeveUrl: div.querySelector('.item-design-left').value,
            rightSleeveUrl: div.querySelector('.item-design-right').value
        };
    });

    if (!confirm(`Fulfill ${items.length} items with ${supplier}?`)) return;

    const originalText = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';

    try {
        // We use the first order's ID as the main reference for the bulk request
        const mainOrderId = items[0].order_id;

        const response = await fetch(`${API_BASE}/orders/${mainOrderId}/fulfill-bulk`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify({
                supplier,
                items,
                printTech,
                printType: supplier === 'Flashship' ? parseInt(flashshipType) : undefined
            })
        });

        const result = await response.json();

        if (result.success) {
            alert(`Success! Bulk order sent to ${supplier}`);
            closeBulkFulfillmentModal();
            loadOrders(); // Refresh order list
            // Clear selection
            const selectAll = document.getElementById('select-all-orders');
            if (selectAll) selectAll.checked = false;
            selectedOrderIds = [];
            onOrderSelectChange();
        } else {
            alert('Fulfillment failed: ' + (result.error || result.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Connection error');
        console.error(e);
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

// Initialize Lucide Icons
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

// Add to refresh function to re-render icons if needed
const originalRefresh = window.refreshOrders || function () { };
window.refreshOrders = async function () {
    await originalRefresh();
    if (typeof lucide !== 'undefined') lucide.createIcons();
};


