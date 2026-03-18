<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>费率配置中心 - 精确版</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: "Microsoft YaHei", sans-serif; font-size: 12px; }
        .main-card { background: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 20px; padding: 20px; }
        
        /* 批量设置按钮 */
        .btn-batch-action { background-color: #0000ff; color: white; border: none; padding: 6px 16px; border-radius: 4px; font-weight: bold; margin-bottom: 15px; }
        
        /* 表格基础样式 */
        .rate-table { border: 1px solid #dee2e6; width: 100%; border-collapse: collapse; }
        .rate-table thead th { background-color: #f8f9fa; border: 1px solid #dee2e6; text-align: center; padding: 6px; font-weight: 500; }
        .rate-table tbody td { border: 1px solid #efefef; padding: 4px; vertical-align: middle; }
        
        /* 录入框样式 */
        .name-input-box { 
            border: 1px solid #dcdcdc !important; 
            text-align: center;
            border-radius: 2px;
            width: 100%;
            padding: 3px;
        }
        .name-input-box:focus { border-color: #4da1ff !important; outline: none; box-shadow: 0 0 3px rgba(77,161,255,0.3); }

        /* 费率数字组 */
        .rate-group { display: flex; align-items: center; border: 1px solid #dcdcdc; border-radius: 2px; background: #fff; width: 70px; margin: 0 auto; }
        .rate-input { border: none; width: 100%; text-align: center; height: 24px; outline: none; font-size: 12px; }
        .unit { color: #999; padding-right: 3px; font-size: 10px; }

        .menu-btn { cursor: pointer; color: #666; font-size: 20px; vertical-align: middle; }
        .sub-link { font-size: 11px; color: #0000ff; text-decoration: none; display: block; margin-bottom: -3px; }
        
        .modal-xl { max-width: 98vw; }
        .btn-add-row { border: 1px dashed #4da1ff; color: #4da1ff; background: #fff; width: 100%; padding: 8px; margin-top: 10px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="main-card">

    <form id="configForm" action="save_step1.php" method="POST">
        <table class="rate-table">
            <thead id="mainHeader"></thead>
            <tbody id="categoryBody"></tbody>
        </table>
        
        <button type="button" class="btn-add-row" onclick="addMainCategory()">+ 插入新游戏分类</button>
        
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary px-5" style="background:#4da1ff; border:none; height: 40px;">确认并保存所有配置</button>
        </div>
    </form>
</div>

<div class="modal fade" id="brandModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header"><h6>设置 [ <span id="curCatName" class="text-primary"></span> ] 的下属品牌特例</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body overflow-auto">
                <table class="rate-table mb-3">
                    <thead id="brandHeader"></thead>
                    <tbody id="brandBody"></tbody>
                </table>
                <button class="btn btn-outline-primary btn-sm" onclick="addRow('brand')">+ 插入新品牌行</button>
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm px-4" onclick="saveBrandData()">暂存品牌配置</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="gameModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light"><h6>设置品牌 [ <span id="curBrandName" class="text-primary"></span> ] 的具体游戏特例</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body overflow-auto">
                <table class="rate-table mb-3">
                    <thead id="gameHeader"></thead>
                    <tbody id="gameBody"></tbody>
                </table>
                <button class="btn btn-outline-success btn-sm" onclick="addRow('game')">+ 插入具体游戏行</button>
            </div>
            <div class="modal-footer"><button class="btn btn-success btn-sm px-4" onclick="saveGameData()">暂存游戏配置</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let activeCatRow = null;
let activeBrandRow = null;
let catCount = 0;

/**
 * 核心：动态表头生成器
 * @param {string} titleName 业务列显示的名称（游戏分类/品牌名称/游戏名称）
 * @param {boolean} showSub 是否显示“下级”列
 */
function generateTheadHTML(titleName, showSub = true) {
    let subCol = showSub ? `<th rowspan="2" width="60">游戏</th>` : `<th rowspan="2" width="60"></th>`;
    let html = `
        <tr>
            <th rowspan="2" width="30"><input type="checkbox"></th>
            <th rowspan="2" width="60">币种</th>
            <th rowspan="2" width="150">${titleName}</th>
            ${subCol}
            <th colspan="2">总代理</th>
            <th colspan="2">1级代理</th>
            <th colspan="2">2级代理</th>
            <th colspan="2">3级代理</th>
            <th colspan="2">4级代理</th>
            <th colspan="2">5级代理</th>
        </tr>
        <tr style="background:#fcfcfc;">
            <th>返水%</th><th>占成%</th>
            <th>返水%</th><th>占成%</th>
            <th>返水%</th><th>占成%</th>
            <th>返水%</th><th>占成%</th>
            <th>返水%</th><th>占成%</th>
            <th>返水%</th><th>占成%</th>
        </tr>
    `;
    return html;
}

// 初始化加载
window.onload = function() {
    document.getElementById('mainHeader').innerHTML = generateTheadHTML('游戏分类 (手动输入)');
    ['体育', '棋牌', '电子'].forEach(name => addMainCategory(name));
};

function addMainCategory(name = '') {
    const tbody = document.getElementById('categoryBody');
    const tr = document.createElement('tr');
    tr.className = 'cat-row';
    tr.innerHTML = `
        <td class="text-center"><input type="checkbox"></td>
        <td class="text-center">PHP</td>
        <td><input type="text" name="cfg[${catCount}][name]" class="name-input-box" value="${name}" placeholder="请输入分类名称"></td>
        <td class="text-center">
            <a href="javascript:void(0)" class="sub-link">覆盖品牌</a>
            <i class="bi bi-list-ul menu-btn" onclick="openBrandModal(this)"></i>
            <textarea class="brands-json d-none" name="cfg[${catCount}][brands]">[]</textarea>
        </td>
        ${Array(12).fill(0).map((_, i) => `
            <td>
                <div class="rate-group">
                    <input type="number" name="cfg[${catCount}][rates][]" class="rate-input" value="0">
                    <span class="unit">%</span>
                </div>
            </td>
        `).join('')}
    `;
    tbody.appendChild(tr);
    catCount++;
}
const bModal = new bootstrap.Modal(document.getElementById('brandModal'));
const gModal = new bootstrap.Modal(document.getElementById('gameModal'));

function openBrandModal(icon) {
    activeCatRow = icon.closest('tr');
    const catName = activeCatRow.querySelector('.name-input-box').value || "未命名分类";
    document.getElementById('curCatName').innerText = catName;
    
    // 动态设置品牌表头
    document.getElementById('brandHeader').innerHTML = generateTheadHTML('品牌名称');
    
    document.getElementById('brandBody').innerHTML = '';
    const data = JSON.parse(activeCatRow.querySelector('.brands-json').value);
    if(data.length === 0) addRow('brand'); else data.forEach(d => addRow('brand', d));
    bModal.show();
}

function openGameModal(icon) {
    activeBrandRow = icon.closest('tr');
    const brandName = activeBrandRow.querySelector('.name-input-box').value || "未命名品牌";
    document.getElementById('curBrandName').innerText = brandName;
    
    // 动态设置游戏表头 (不显示下级列图标)
    document.getElementById('gameHeader').innerHTML = generateTheadHTML('具体游戏名称', false);
    
    document.getElementById('gameBody').innerHTML = '';
    const data = JSON.parse(activeBrandRow.querySelector('.games-json').value);
    if(data.length === 0) addRow('game'); else data.forEach(d => addRow('game', d));
    gModal.show();
}

function addRow(type, data = null) {
    const tbody = document.getElementById(type + 'Body');
    const tr = document.createElement('tr');
    tr.className = type + '-item-row';
    
    let subIcon = type === 'brand' ? 
        `<i class="bi bi-list-ul menu-btn" onclick="openGameModal(this)"></i><textarea class="games-json d-none">${data ? JSON.stringify(data.games) : '[]'}</textarea>` : `-`;

    let html = `<td class="text-center"><input type="checkbox"></td><td class="text-center">PHP</td>
                <td><input type="text" class="name-input-box" value="${data ? data.name : ''}" placeholder="输入名称"></td>
                <td class="text-center">${subIcon}</td>`;
    
    for(let i=0; i<12; i++) {
        let val = (data && data.rates) ? data.rates[i] : 0;
        html += `<td><div class="rate-group"><input type="number" class="rate-input" value="${val}"><span class="unit">%</span></div></td>`;
    }
    tr.innerHTML = html;
    tbody.appendChild(tr);
}

// 暂存逻辑
function saveGameData() {
    const games = Array.from(document.querySelectorAll('.game-item-row')).map(row => ({
        name: row.querySelector('.name-input-box').value,
        rates: Array.from(row.querySelectorAll('.rate-input')).map(i => i.value)
    })).filter(g => g.name);
    activeBrandRow.querySelector('.games-json').value = JSON.stringify(games);
    gModal.hide();
}

function saveBrandData() {
    const brands = Array.from(document.querySelectorAll('.brand-item-row')).map(row => ({
        name: row.querySelector('.name-input-box').value,
        rates: Array.from(row.querySelectorAll('.rate-input')).map(i => i.value),
        games: JSON.parse(row.querySelector('.games-json').value)
    })).filter(b => b.name);
    activeCatRow.querySelector('.brands-json').value = JSON.stringify(brands);
    bModal.hide();
}
</script>
</body>
</html>