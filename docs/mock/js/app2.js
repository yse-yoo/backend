let cart = [];
let receiptNo = 1;

// --- 画面切り替え機能 ---
function switchView(viewName) {
    // 全てのセクションを非表示にする
    document.querySelectorAll('.view-section').forEach(section => {
        section.classList.add('hidden');
    });
    // 対象のセクションを表示
    document.getElementById(`view-${viewName}`).classList.remove('hidden');

    // ナビボタンのスタイル更新
    document.querySelectorAll('.nav-item').forEach(btn => {
        btn.classList.remove('text-blue-600', 'bg-blue-50');
        btn.classList.add('text-gray-500');
    });
    // クリックされたボタン（または対応するボタン）をアクティブにする処理は省略（動的制御が必要な場合）
}

// 管理者ログイン用
function handleLogin() {
    const pass = prompt('管理者パスワードを入力してください');
    if(pass === 'admin') {
        alert('ログイン成功');
    } else {
        alert('パスワードが違います');
    }
}

// --- レジ機能 (前回のロジックを継承) ---
const fmt = (n) => '¥' + n.toLocaleString('ja-JP');

function renderProducts(cat) {
    const grid = document.getElementById('product-grid');
    const list = cat === '全て' ? allProducts : allProducts.filter(p => p.cat === cat);
    
    grid.innerHTML = list.map(p => `
        <button onclick="addItem(${p.id})" class="bg-white border border-gray-200 rounded-lg p-3 flex flex-col items-center gap-1 hover:border-blue-300 hover:bg-blue-50 transition-all shadow-sm">
            <span class="text-2xl">${p.icon}</span>
            <span class="text-md font-bold text-gray-800">${p.name}</span>
            <span class="text-md text-gray-400">${fmt(p.price)}</span>
        </button>`).join('');
}

function filterCat(el, cat) {
    document.querySelectorAll('.tab-btn').forEach(t => {
        t.classList.remove('active', 'bg-blue-600', 'text-white', 'border-blue-600');
        t.classList.add('bg-white', 'text-gray-500', 'border-gray-300');
    });
    el.classList.add('active', 'bg-blue-600', 'text-white', 'border-blue-600');
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
        el.innerHTML = '<div class="text-gray-400 text-sm text-center py-8">商品を選択してください</div>';
        updateTotal(0);
        return;
    }
    el.innerHTML = cart.map(item => `
        <div class="flex items-center gap-2 py-2 border-b border-gray-50 last:border-none">
            <span class="flex-1 text-md font-medium">${item.icon} ${item.name}</span>
            <div class="flex items-center gap-2">
                <button onclick="changeQty(${item.id},-1)" class="w-5 h-5 rounded-full border flex items-center justify-center text-xs hover:bg-gray-100">−</button>
                <span class="text-md font-bold min-w-[16px] text-center">${item.qty}</span>
                <button onclick="changeQty(${item.id},1)" class="w-5 h-5 rounded-full border flex items-center justify-center text-xs hover:bg-gray-100">+</button>
            </div>
            <span class="text-xs font-bold min-w-[50px] text-right">${fmt(item.price * item.qty)}</span>
            <button onclick="removeItem(${item.id})" class="text-gray-300 hover:text-red-500 px-1">✕</button>
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
    cart = [];
    renderCart();
}

function confirmPay(method) {
    if (cart.length === 0) return alert('商品を選択してください');
    alert(`会計完了（${method}）\nありがとうございました。`);
    cart = [];
    receiptNo++;
    document.getElementById('receipt-num').textContent = '伝票 #' + String(receiptNo).padStart(4, '0');
    renderCart();
}

function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('ja-JP', { hour12: false });
}

// 初期化
renderProducts('全て');
updateClock();
setInterval(updateClock, 1000);