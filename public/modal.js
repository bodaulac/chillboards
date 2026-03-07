/**
 * ORDER DETAIL MODAL
 * Display full order information when clicking on a row
 */

/**
 * Show order detail modal
 */
function showOrderDetail(order) {
    const modal = document.getElementById('order-detail-modal');
    if (!modal) {
        console.error('Modal not found');
        return;
    }

    // Populate modal content
    document.getElementById('modal-order-id').textContent = order['Order ID'] || order.orderId || 'N/A';
    document.getElementById('modal-platform').textContent = order.Platform || order.platform || 'N/A';
    document.getElementById('modal-date').textContent = formatDate(order.Date || order.date);
    document.getElementById('modal-status').textContent = order.Status || order.status || 'PENDING';

    // Customer info
    const firstName = order.FirstName || order.firstName || '';
    const lastName = order.LastName || order.lastName || '';
    document.getElementById('modal-customer-name').textContent = `${firstName} ${lastName}`.trim() || 'N/A';
    document.getElementById('modal-customer-address').textContent =
        `${order.AddressLine1 || ''} ${order.AddressLine2 || ''}`.trim() || 'N/A';
    document.getElementById('modal-customer-city').textContent =
        `${order.City || ''}, ${order.StateOrRegion || ''} ${order.Zip || ''}`.trim() || 'N/A';
    document.getElementById('modal-customer-phone').textContent = order.Phone || order.phone || 'N/A';

    // Product info
    document.getElementById('modal-sku').textContent = order.SKU || order.sku || 'N/A';
    document.getElementById('modal-size').textContent = order.Size || order.size || 'N/A';
    document.getElementById('modal-color').textContent = order.Color || order.color || 'N/A';
    document.getElementById('modal-quantity').textContent = order.Quantity || order.quantity || '1';

    // Production info
    document.getElementById('modal-production-type').textContent = order['PRODUCTION TYPE'] || order.productionType || 'DTF';
    document.getElementById('modal-supplier').textContent = order.Supplier || order.supplier || 'flashship';
    document.getElementById('modal-designer').textContent = order.DESIGNER || order.designer || 'Not assigned';

    // Design info
    const printLocation = order['PRINT LOCATION'] || order.printLocation || '';
    const designUrl = order.DESIGNURL || order.designUrl || '';
    const mockupUrl = order.MOCKUPURL || order.mockupUrl || '';

    document.getElementById('modal-print-location').textContent = printLocation || 'Not set';

    if (designUrl) {
        document.getElementById('modal-design-url').innerHTML = `<a href="${designUrl}" target="_blank">View Design</a>`;
    } else {
        document.getElementById('modal-design-url').textContent = 'Not uploaded';
    }

    if (mockupUrl) {
        document.getElementById('modal-mockup-url').innerHTML = `<a href="${mockupUrl}" target="_blank">View Mockup</a>`;
    } else {
        document.getElementById('modal-mockup-url').textContent = 'Not uploaded';
    }

    // Tracking
    document.getElementById('modal-tracking').textContent = order.Tracking || order.tracking || 'Not available';

    // Show modal
    modal.classList.add('active');
}

/**
 * Close order detail modal
 */
function closeOrderDetail() {
    const modal = document.getElementById('order-detail-modal');
    if (modal) {
        modal.classList.remove('active');
    }
}

/**
 * Format date helper
 */
function formatDate(date) {
    if (!date) return 'N/A';
    try {
        return new Date(date).toLocaleDateString();
    } catch (e) {
        return date;
    }
}

/**
 * Get product image URL from SKU
 * This is a placeholder - you should implement actual image mapping
 */
function getProductImageUrl(sku) {
    if (!sku) return null;

    // TODO: Implement actual image mapping
    // For now, return placeholder based on SKU pattern

    // Example: If you have images stored in Google Drive or CDN
    // return `https://your-cdn.com/products/${sku}.jpg`;

    return null; // Return null to show placeholder
}

// Add to existing app.js file
