let cart = [];
let receiptNo = 1;
const fmt = (n) => '¥' + n.toLocaleString('ja-JP');

function switchView(viewName) {
    document.querySelectorAll('.view-section').forEach(s => s.classList.add('hidden'));
    const target = document.getElementById(`view-${viewName}`);
    if(target) target.classList.remove('hidden');
}

function renderProducts(cat) {
    const grid = document.getElementById('product-grid');
    const list = cat === '全て' ? allProducts : allProducts.filter(p => p.cat === cat);
    
    grid.innerHTML = list.map(p => `
        <button onclick="addItem(${p.id})" class="bg-white border border-slate-100 rounded-xl p-4 flex flex-col items-center gap-3 hover:border-brand/40 hover:bg-brand/5 transition-all shadow-sm active:scale-95 group">
            <span class="text-3xl group-hover:scale-110 transition-transform">${p.icon}</span>
            <div class="text-center">
                <span class="text-sm font-bold text-slate-700 block truncate w-32">${p.name}</span>
                <span class="text-md font-black text-brand-dark">${fmt(p.price)}</span>
            </div>
        </button>`).join('');
}

function filterCat(el, cat) {
    document.querySelectorAll('.tab-btn').forEach(t => {
        t.className = "tab-btn px-6 py-2 rounded-lg bg-slate-50 text-slate-500 text-sm font-bold hover:bg-brand/5 border border-transparent hover:border-brand/20 transition-all";
    });
    el.className = "tab-btn active px-6 py-2 rounded-lg bg-brand text-white text-sm font-bold shadow-sm shadow-brand/20";
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

function removeItem(id) {
    cart = cart.filter(x => x.id !== id);
    renderCart();
}

function renderCart() {
    const el = document.getElementById('receipt-items');
    if (cart.length === 0) {
        el.innerHTML = '<div class="text-center py-20 text-slate-300"><p class="text-sm font-bold italic">No items selected</p></div>';
        updateTotal(0);
        return;
    }
    el.innerHTML = cart.map(item => `
        <div class="flex items-center gap-3 py-3 border-b border-slate-50 last:border-none animate-in fade-in slide-in-from-right-2 duration-200">
            <span class="text-2xl">${item.icon}</span>
            <div class="flex-1 min-w-0">
                <span class="text-sm font-bold text-slate-700 block truncate">${item.name}</span>
                <span class="text-[10px] text-slate-400 font-mono">${fmt(item.price)} / unit</span>
            </div>
            <div class="flex items-center gap-1 bg-slate-50 rounded-lg p-1">
                <button onclick="changeQty(${item.id},-1)" class="w-7 h-7 rounded-md bg-white border border-slate-200 flex items-center justify-center text-sm font-bold hover:bg-white transition-colors">−</button>
                <span class="text-sm font-mono font-bold min-w-[24px] text-center">${item.qty}</span>
                <button onclick="changeQty(${item.id},1)" class="w-7 h-7 rounded-md bg-white border border-slate-200 flex items-center justify-center text-sm font-bold hover:bg-white transition-colors">+</button>
            </div>
            <span class="text-md font-mono font-bold min-w-[80px] text-right text-brand-dark">${fmt(item.price * item.qty)}</span>
        </div>`).join('');
    
    const sub = cart.reduce((s, i) => s + i.price * i.qty, 0);
    updateTotal(sub);
}

function updateTotal(sub) {
    const tax = Math.round(sub * 0.1);
    document.getElementById('subtotal').textContent = fmt(sub);
    document.getElementById('tax').textContent = fmt(tax);
    document.getElementById('total').textContent = fmt(sub + tax);
}

function clearOrder() {
    if(cart.length > 0 && confirm('注文をすべて削除しますか？')) {
        cart = [];
        renderCart();
    }
}

function confirmPay(method) {
    if (cart.length === 0) return;
    const total = document.getElementById('total').textContent;
    alert(`会計完了: ${total}`);
    cart = [];
    receiptNo++;
    document.getElementById('receipt-num').textContent = 'NO.' + String(receiptNo).padStart(4, '0');
    renderCart();
}

function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('ja-JP', { hour12: false });
}

setInterval(updateClock, 1000);
updateClock();
renderProducts('全て');