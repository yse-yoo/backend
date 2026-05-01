let cart = [];
let receiptNo = 1;

const fmt = (n) => '¥' + n.toLocaleString('ja-JP');

// --- 画面切り替え ---
function switchView(el, viewName) {
    document.querySelectorAll('.view-section').forEach(s => s.classList.add('hidden'));
    document.getElementById(`view-${viewName}`).classList.remove('hidden');

    // サイドバーのスタイル更新
    document.querySelectorAll('.nav-item').forEach(btn => {
        btn.classList.remove('bg-sky-600', 'text-white');
        btn.classList.add('text-slate-400');
    });
    
    // クリックされたボタンをアクティブ化
    if(el) {
        el.classList.remove('text-slate-400');
        el.classList.add('bg-sky-600', 'text-white');
    }
}

// --- 商品表示 ---
function renderProducts(cat) {
    const grid = document.getElementById('product-grid');
    const list = cat === '全て' ? allProducts : allProducts.filter(p => p.cat === cat);
    
    grid.innerHTML = list.map(p => `
        <button onclick="addItem(${p.id})" class="bg-white border border-slate-100 rounded-xl p-4 flex flex-col items-center gap-2 hover:border-sky-300 hover:shadow-md transition-all active:scale-95 shadow-sm">
            <span class="text-3xl">${p.icon}</span>
            <span class="text-sm font-bold text-slate-700">${p.name}</span>
            <span class="text-xs font-semibold text-sky-600 bg-sky-50 px-3 py-0.5 rounded-full">${fmt(p.price)}</span>
        </button>`).join('');
}

function filterCat(el, cat) {
    document.querySelectorAll('.tab-btn').forEach(t => {
        t.classList.remove('border-sky-600', 'bg-sky-600', 'text-white', 'shadow-sky-100');
        t.classList.add('border-slate-200', 'bg-white', 'text-slate-600');
    });
    el.classList.remove('border-slate-200', 'bg-white', 'text-slate-600');
    el.classList.add('border-sky-600', 'bg-sky-600', 'text-white', 'shadow-sky-100');
    renderProducts(cat);
}

function addItem(id) {
    const p = allProducts.find(x => x.id === id);
    const existing = cart.find(x => x.id === id);
    if (existing) { existing.qty += 1; } 
    else { cart.push({ ...p, qty: 1 }); }
    renderCart();
}

function changeQty(id, delta) {
    const i = cart.findIndex(x => x.id === id);
    if (i === -1) return;
    cart[i].qty += delta;
    if (cart[i].qty <= 0) cart.splice(i, 1);
    renderCart();
}

function renderCart() {
    const el = document.getElementById('receipt-items');
    if (cart.length === 0) {
        el.innerHTML = '<div class="text-slate-300 text-sm text-center py-10 font-medium">商品を選択してください</div>';
        updateTotal(0);
        return;
    }
    el.innerHTML = cart.map(item => `
        <div class="flex items-center gap-3 py-3 border-b border-slate-50 last:border-none">
            <div class="flex-1">
                <div class="text-sm font-bold text-slate-700">${item.name}</div>
                <div class="text-[10px] text-slate-400">${fmt(item.price)}</div>
            </div>
            <div class="flex items-center gap-2 bg-slate-100 rounded-lg p-0.5">
                <button onclick="changeQty(${item.id},-1)" class="w-6 h-6 rounded bg-white shadow-sm flex items-center justify-center text-xs font-bold hover:bg-sky-50">−</button>
                <span class="text-sm font-bold min-w-[20px] text-center">${item.qty}</span>
                <button onclick="changeQty(${item.id},1)" class="w-6 h-6 rounded bg-white shadow-sm flex items-center justify-center text-xs font-bold hover:bg-sky-50">+</button>
            </div>
            <span class="text-sm font-bold min-w-[70px] text-right text-slate-800">${fmt(item.price * item.qty)}</span>
        </div>`).join('');
    
    const sub = cart.reduce((s, i) => s + i.price * i.qty, 0);
    updateTotal(sub);
}

function updateTotal(sub) {
    const tax = Math.round(sub * 0.1);
    document.getElementById('subtotal').textContent = fmt(sub);
    document.getElementById('total').textContent = fmt(sub + tax);
}

function clearOrder() {
    if(cart.length > 0 && confirm('カートを空にしますか？')) {
        cart = [];
        renderCart();
    }
}

function confirmPay(method) {
    if (cart.length === 0) return;
    const total = document.getElementById('total').textContent;
    alert(`会計完了: ${total}\nご利用ありがとうございました。`);
    cart = [];
    receiptNo++;
    document.getElementById('receipt-num').textContent = '#' + String(receiptNo).padStart(4, '0');
    renderCart();
}

function updateClock() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('ja-JP', { hour12: false });
    if(document.getElementById('clock')) document.getElementById('clock').textContent = timeStr;
    if(document.getElementById('clock-desktop')) document.getElementById('clock-desktop').textContent = timeStr;
}

setInterval(updateClock, 1000);
updateClock();
renderProducts('全て');