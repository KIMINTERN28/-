<?php
$configPath = 'data/config.json';
$configData = file_exists($configPath) ? file_get_contents($configPath) : '[]';
$config = json_decode($configData, true);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>Step 2 - 智能业绩录入系统 (百分比版)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --brand-color: #4da1ff; --bg-gray: #f4f7f6; }
        body { background-color: var(--bg-gray); font-size: 12px; scroll-behavior: smooth; }
        
        /* 侧边导航 */
        .level-nav { position: fixed; left: 20px; top: 150px; display: flex; flex-direction: column; gap: 8px; z-index: 1000; }
        .level-nav a { 
            width: 42px; height: 42px; border-radius: 8px; background: #fff; border: 1px solid #ddd;
            display: flex; align-items: center; justify-content: center; text-decoration: none;
            color: #666; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: 0.2s;
        }
        .level-nav a:hover { background: var(--brand-color); color: #fff; border-color: var(--brand-color); }

        /* 卡片增强 */
        .agent-block { background: #fff; margin: 20px 20px 40px 80px; border-radius: 12px; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; }
        .agent-header { background: #fff; padding: 15px 20px; border-bottom: 1px solid #eee; font-weight: bold; }
        
        /* 固定表头 */
        .perf-table thead th { 
            position: sticky; top: -1px; z-index: 10; 
            background: #f8f9fa !important; border-top: none;
            box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
        }

        /* 颜色语义化 */
        .my-cyan { background-color: #e3fcf2 !important; color: #0d6832; }
        .blue-zone { background-color: #eaf4ff !important; color: #0056b3; }
        .input-sm { font-size: 11px; height: 28px; border-radius: 4px; }
        
        /* 输入高亮 */
        .form-control:focus { background-color: #fff9db !important; border-color: #ffda6a; box-shadow: 0 0 0 0.2rem rgba(255, 218, 106, 0.25); }
        
        .btn-add-perf { border: 1px dashed var(--brand-color); color: var(--brand-color); background: #fff; width: 100%; padding: 8px; border-radius: 6px; transition: 0.3s; }
        .btn-add-perf:hover { background: #f0f7ff; }
        
        .sticky-bottom-bar { position: fixed; bottom: 0; width: 100%; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); padding: 15px; box-shadow: 0 -5px 15px rgba(0,0,0,0.1); z-index: 1000; }
        .unit-tag { font-size: 10px; color: #999; margin-left: 2px; }
    </style>
</head>
<body>

<div class="level-nav d-none d-xl-flex">
    <?php for($i=0; $i<6; $i++): ?>
    <a href="#agt_anchor_<?=$i?>">L<?=$i?></a>
    <?php endfor; ?>
</div>

<div class="container-fluid pb-5">
    <form id="perfForm" action="calculate_final.php" method="POST">
        <div class="d-flex justify-content-between align-items-center p-3 ms-5">
            <h4>Step 2: 业绩录入 <small class="text-muted" style="font-size:12px;">(成本率已改为百分比录入模式)</small></h4>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="localStorage.removeItem('perf_draft'); location.reload();">重置清空</button>
            </div>
        </div>

        <?php 
        $levels = ["总代(L0)", "一级(L1)", "二级(L2)", "三级(L3)", "四级(L4)", "五级(L5)"];
        foreach ($levels as $lIdx => $levelName): 
        ?>
        <div class="agent-block" id="agt_anchor_<?=$lIdx?>">
            <div class="agent-header d-flex align-items-center">
                <span class="badge bg-primary me-2">L<?=$lIdx?></span>
                <input type="text" name="agt[<?=$lIdx?>][name]" class="form-control form-control-sm me-3" style="width:150px" placeholder="代理账号" required>
                
                <div class="input-group input-group-sm w-auto me-2 shadow-sm">
                    <span class="input-group-text my-cyan">手续%</span>
                    <input type="number" step="0.01" name="agt[<?=$lIdx?>][ryx]" class="form-control input-sm" style="width:60px" value="0">
                </div>
                <div class="input-group input-group-sm w-auto me-2 shadow-sm">
                    <span class="input-group-text my-cyan">支付%</span>
                    <input type="number" step="0.01" name="agt[<?=$lIdx?>][rzf]" class="form-control input-sm" style="width:60px" value="0">
                </div>
                <div class="input-group input-group-sm w-auto me-2 shadow-sm">
                    <span class="input-group-text my-cyan">优惠%</span>
                    <input type="number" step="0.01" name="agt[<?=$lIdx?>][ryh]" class="form-control input-sm" style="width:60px" value="0">
                </div>
                <div class="input-group input-group-sm w-auto shadow-sm">
                    <span class="input-group-text bg-warning text-dark fw-bold">成本率%</span>
                    <input type="number" step="0.1" name="agt[<?=$lIdx?>][costRate]" class="form-control input-sm fw-bold" style="width:60px; color:red;" value="10">
                </div>
            </div>

            <div class="p-3">
                <table class="table table-bordered perf-table mb-0">
                    <thead>
                        <tr>
                            <th width="12%">分类</th>
                            <th width="12%">品牌</th>
                            <th width="12%">游戏</th>
                            <th class="blue-zone">输赢(GGR)</th>
                            <th class="blue-zone">总投注额</th>
                            <th class="my-cyan">充值</th>
                            <th class="my-cyan">提现</th>
                            <th class="my-cyan">优惠</th>
                            <th width="40"></th>
                        </tr>
                    </thead>
                    <tbody id="perf_body_<?=$lIdx?>"></tbody>
                </table>
                <button type="button" class="btn-add-perf btn-sm mt-2" onclick="addPerfRow(<?=$lIdx?>)">+ 为 <?= $levelName ?> 添加一行业绩</button>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="sticky-bottom-bar text-center">
            <button type="submit" class="btn btn-primary px-5 shadow-lg" style="font-weight:bold; height:48px; border-radius: 24px;">
                <i class="bi bi-calculator-fill me-2"></i>保存并执行
            </button>
        </div>
    </form>
</div>

<script>
const rawConfig = <?= $configData ?>;
let globalRowId = 0;
let isChanged = false;

function addPerfRow(lIdx) {
    const tbody = document.getElementById('perf_body_' + lIdx);
    const rowId = globalRowId++;
    const tr = document.createElement('tr');
    
    let catOptions = '<option value="">请选择分类</option>';
    rawConfig.forEach((cat, index) => {
        catOptions += `<option value="${index}">${cat.category_name}</option>`;
    });

    tr.innerHTML = `
        <td><select name="agt[${lIdx}][p][${rowId}][cat_idx]" class="form-select form-select-sm" onchange="updateBrands(this, ${lIdx}, ${rowId})">${catOptions}</select></td>
        <td><select name="agt[${lIdx}][p][${rowId}][brand_idx]" class="form-select form-select-sm" onchange="updateGames(this, ${lIdx}, ${rowId})"><option value="">全部品牌</option></select></td>
        <td><select name="agt[${lIdx}][p][${rowId}][game_idx]" class="form-select form-select-sm"><option value="">全部游戏</option></select></td>
        <td class="blue-zone"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][ggr]" class="form-control input-sm" value="0"></td>
        <td class="blue-zone"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][bet]" class="form-control input-sm" value="0"></td>
        <td class="my-cyan"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][cz]" class="form-control input-sm" value="0"></td>
        <td class="my-cyan"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][tx]" class="form-control input-sm" value="0"></td>
        <td class="my-cyan"><input type="number" step="0.01" name="agt[${lIdx}][p][${rowId}][yh]" class="form-control input-sm" value="0"></td>
        <td class="text-center"><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    isChanged = true;
}

function updateBrands(catSelect, lIdx, rowId) {
    const brandSelect = catSelect.closest('tr').querySelector(`select[name*="brand_idx"]`);
    const gameSelect = catSelect.closest('tr').querySelector(`select[name*="game_idx"]`);
    const catIdx = catSelect.value;
    brandSelect.innerHTML = '<option value="">全部品牌</option>';
    gameSelect.innerHTML = '<option value="">全部游戏</option>';
    if (catIdx !== "" && rawConfig[catIdx].brands) {
        rawConfig[catIdx].brands.forEach((brand, bIndex) => {
            brandSelect.innerHTML += `<option value="${bIndex}">${brand.brand_name}</option>`;
        });
    }
}

function updateGames(brandSelect, lIdx, rowId) {
    const catSelect = brandSelect.closest('tr').querySelector(`select[name*="cat_idx"]`);
    const gameSelect = brandSelect.closest('tr').querySelector(`select[name*="game_idx"]`);
    const catIdx = catSelect.value;
    const brandIdx = brandSelect.value;
    gameSelect.innerHTML = '<option value="">全部游戏</option>';
    if (catIdx !== "" && brandIdx !== "" && rawConfig[catIdx].brands[brandIdx].games) {
        rawConfig[catIdx].brands[brandIdx].games.forEach((game, gIndex) => {
            gameSelect.innerHTML += `<option value="${gIndex}">${game.name}</option>`;
        });
    }
}

// 自动全选
document.addEventListener('focusin', (e) => {
    if (e.target.tagName === 'INPUT' && e.target.type === 'number') e.target.select();
});

// 防丢拦截
window.onbeforeunload = function() {
    if (isChanged) return "检测到您有未保存的数据，确定要离开吗？";
};
document.getElementById('perfForm').onsubmit = () => { window.onbeforeunload = null; };

window.onload = () => { for(let i=0; i<6; i++) addPerfRow(i); };
</script>
</body>
</html>