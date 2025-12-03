// Debug link laporan penjualan
document.addEventListener("DOMContentLoaded", function() {
    const laporanLink = document.getElementById('laporanLink');
    console.log("Laporan Penjualan Link:", laporanLink);
    
    if (laporanLink) {
        laporanLink.addEventListener('click', function(e) {
            console.log("Laporan Penjualan clicked!");
            console.log("Going to:", this.href);
        
        });
    } else {
        console.log("Laporan Penjualan link NOT FOUND - mungkin kondisi admin tidak terpenuhi");
    }
});

let cart = [];

function addToCart(item) {
    const existingItem = cart.find(i => i.menu_id === item.menu_id);
    
    if (existingItem) {
        if (existingItem.quantity < item.stock) {
            existingItem.quantity++;
        } else {
            alert('Stock tidak cukup!');
            return;
        }
    } else {
        cart.push({
            menu_id: item.menu_id,
            name: item.name,
            price: parseFloat(item.price),
            quantity: 1,
            stock: parseInt(item.stock)
        });
    }
    
    updateCart();
}

function removeFromCart(menuId) {
    cart = cart.filter(item => item.menu_id !== menuId);
    updateCart();
}

function updateQuantity(menuId, change) {
    const item = cart.find(i => i.menu_id === menuId);
    if (item) {
        const newQty = item.quantity + change;
        if (newQty > 0 && newQty <= item.stock) {
            item.quantity = newQty;
            updateCart();
        } else if (newQty > item.stock) {
            alert('Stock tidak cukup!');
        }
    }
}

function updateCart() {
    const cartItems = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartCount');
    const totalPrice = document.getElementById('totalPrice');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const cartData = document.getElementById('cartData');
    
    if (cart.length === 0) {
        cartItems.innerHTML = '<div class="empty-cart"><p>Keranjang masih kosong</p><p>Silakan pilih menu</p></div>';
        checkoutBtn.disabled = true;
    } else {
        let html = '';
        let total = 0;
        
        cart.forEach(item => {
            const subtotal = item.price * item.quantity;
            total += subtotal;
            
            html += `
                <div class="cart-item">
                    <div class="cart-item-header">
                        <h4>${item.name}</h4>
                        <button class="remove-btn" onclick="removeFromCart(${item.menu_id})">Hapus</button>
                    </div>
                    <div>Rp ${item.price.toLocaleString('id-ID')}</div>
                    <div class="quantity-control">
                        <button class="qty-btn" onclick="updateQuantity(${item.menu_id}, -1)">-</button>
                        <span class="qty-display">${item.quantity}</span>
                        <button class="qty-btn" onclick="updateQuantity(${item.menu_id}, 1)">+</button>
                        <span style="margin-left: auto; font-weight: bold;">
                            Rp ${subtotal.toLocaleString('id-ID')}
                        </span>
                    </div>
                </div>
            `;
        });
        
        cartItems.innerHTML = html;
        totalPrice.textContent = 'Rp ' + total.toLocaleString('id-ID');
        checkoutBtn.disabled = false;
    }
    
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartCount.textContent = totalItems;
    cartData.value = JSON.stringify(cart);
}

function filterMenu(category) {
    const cards = document.querySelectorAll('.menu-card');
    const buttons = document.querySelectorAll('.filter-btn');
    
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    cards.forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function scrollToCart() {
    document.getElementById('cartSection').scrollIntoView({ behavior: 'smooth' });
}

function closeModal() {
    document.getElementById('orderModal').classList.remove('show');
    window.location.href = 'index.php';
}

// ============ TOGGLE SIDEBAR ===============
document.addEventListener("DOMContentLoaded", () => {
    const burgerBtn = document.getElementById("burgerBtn");
    const sidebar = document.getElementById("sidebar");

    if (burgerBtn && sidebar) {
        burgerBtn.addEventListener("click", () => {
            sidebar.classList.toggle("active");
        });
    }
});

// ============ CLOSE SIDEBAR WITH <= BUTTON ===============
document.addEventListener("DOMContentLoaded", () => {
    const closeBtn = document.getElementById("closeSidebarBtn");
    const sidebar = document.getElementById("sidebar");

    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            sidebar.classList.remove("active");
        });
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById("sidebar");
    const burgerBtn = document.getElementById("burgerBtn");
    const closeBtn = document.getElementById("closeSidebarBtn");

    // buka sidebar
    if (burgerBtn) {
        burgerBtn.addEventListener("click", () => {
            sidebar.classList.add("active");
            console.log("Sidebar opened");
        });
    }

    // tutup sidebar
    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            sidebar.classList.remove("active");
            console.log("Sidebar closed");
        });
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById("sidebar");
    const burgerBtn = document.getElementById("burgerBtn");
    const closeBtn = document.getElementById("closeSidebarBtn");

    console.log("Elements:", {sidebar, burgerBtn, closeBtn});

    // buka sidebar
    if (burgerBtn) {
        burgerBtn.addEventListener("click", () => {
            console.log("Burger button clicked");
            sidebar.classList.add("active");
            console.log("Sidebar classes:", sidebar.classList);
        });
    }

    // tutup sidebar
    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            sidebar.classList.remove("active");
            console.log("Sidebar closed");
        });
    }
});

