let currentInputCode = "";

// モーダルを開く
function openCodeModal() {
    currentInputCode = "";
    updateCodeDisplay();
    document.getElementById('code-modal').classList.remove('hidden');
}

// モーダルを閉じる
function closeCodeModal() {
    document.getElementById('code-modal').classList.add('hidden');
}

// 数字入力
function pressKey(num) {
    if (currentInputCode.length < 4) { // 例として4桁まで
        currentInputCode += num;
        updateCodeDisplay();
    }
}

// クリア
function clearKey() {
    currentInputCode = "";
    updateCodeDisplay();
}

// 表示更新
function updateCodeDisplay() {
    document.getElementById('code-display').textContent = currentInputCode;
}

// 確定処理
function submitCode() {
    if (currentInputCode === "") return;
    
    // 文字列を数値に変換（idと比較するため）
    const targetId = parseInt(currentInputCode);
    const product = allProducts.find(p => p.id === targetId);
    
    if (product) {
        addItem(targetId);
        closeCodeModal();
    } else {
        alert("該当する商品がありません");
        clearKey();
    }
}

// --- 既存のボタンへの割り当て修正 ---
// HTML側の <button onclick="" class="tab-btn ...">商品コード</button> を
// <button onclick="openCodeModal()" ...> に書き換えてください。