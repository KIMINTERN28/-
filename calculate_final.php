<?php
/**
 * 代理结算对账单 - 财务明细穿透版
 */

$configPath = 'data/config.json';
$config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];
$input = $_POST['agt'] ?? [];

$agentReports = [];

function getRate($lIdx, $p, $config) {
    $idx_fy = $lIdx * 2; $idx_zc = $lIdx * 2 + 1;
    $f_fy = 0; $f_zc = 0;
    $info = ['cat' => '未知', 'brand' => '-'];
    if ($p['cat_idx'] !== "" && isset($config[$p['cat_idx']])) {
        $c = $config[$p['cat_idx']];
        $info['cat'] = $c['category_name'];
        $f_fy = (float)($c['category_rates'][$idx_fy] ?? 0) / 100;
        $f_zc = (float)($c['category_rates'][$idx_zc] ?? 0) / 100;
        if ($p['brand_idx'] !== "" && isset($c['brands'][$p['brand_idx']])) {
            $b = $c['brands'][$p['brand_idx']];
            $info['brand'] = $b['brand_name'];
            $f_fy = (float)($b['brand_rates'][$idx_fy] ?? $f_fy * 100) / 100;
            $f_zc = (float)($b['brand_rates'][$idx_zc] ?? $f_zc * 100) / 100;
        }
    }
    return ['fy' => $f_fy, 'zc' => $f_zc, 'info' => $info];
}

// 1. 初始化并计算直属
foreach ($input as $lIdx => $agt) {
    $details = [];
    $directPerf = []; 
    $r_sx = (float)($agt['ryx'] ?? 0); $r_zf = (float)($agt['rzf'] ?? 0); $r_df = (float)($agt['ryh'] ?? 0); 
    
    if (isset($agt['p']) && is_array($agt['p'])) {
        foreach ($agt['p'] as $p) {
            $rates = getRate($lIdx, $p, $config);
            $ggr = (float)($p['ggr'] ?? 0); $bet = (float)($p['bet'] ?? 0);
            $cz = (float)($p['cz'] ?? 0); $tx = (float)($p['yh'] ?? 0);

            $item_profit = ((-$ggr * $rates['zc']) + ($bet * $rates['fy'])) - 
                          ((abs($ggr) * $r_sx/100) + ($cz * $r_zf/100) + ($tx * $r_df/100));

            $details[] = [
                'name' => $rates['info']['cat'] . " / " . $rates['info']['brand'],
                'ggr' => $ggr, 'bet' => $bet,
                'rate_zc' => ($rates['zc']*100)."%", 'rate_fs' => ($rates['fy']*100)."%",
                'costs' => ['sx' => abs($ggr)*$r_sx/100, 'zf' => $cz*$r_zf/100, 'df' => $tx*$r_df/100, 'r_sx'=>$r_sx, 'r_zf'=>$r_zf, 'r_df'=>$r_df],
                'profit' => $item_profit
            ];
            $key = $p['cat_idx']."_".$p['brand_idx']."_".$p['game_idx'];
            if(!isset($directPerf[$key])) $directPerf[$key] = ['ggr'=>0, 'bet'=>0, 'source'=>$p];
            $directPerf[$key]['ggr'] += $ggr; $directPerf[$key]['bet'] += $bet;
        }
    }
    $agentReports[$lIdx] = [
        'name' => $agt['name'] ?: "代理 L".$lIdx,
        'details' => $details,
        'directPerf' => $directPerf,
        'teamTotalPerf' => [],
        'costRate' => (float)($agt['costRate'] ?? 0),
        'subTotalProfit' => 0, 'finalSubEarn' => 0, 'subGgr' => 0, 'subBet' => 0,
        'subDiffDetails' => [] // 差额明细存储
    ];
}

// 2. 向上汇总业绩
for ($i = count($agentReports)-1; $i >= 0; $i--) {
    foreach ($agentReports[$i]['directPerf'] as $k => $v) {
        if (!isset($agentReports[$i]['teamTotalPerf'][$k])) $agentReports[$i]['teamTotalPerf'][$k] = ['ggr'=>0, 'bet'=>0, 'source'=>$v['source']];
        $agentReports[$i]['teamTotalPerf'][$k]['ggr'] += $v['ggr'];
        $agentReports[$i]['teamTotalPerf'][$k]['bet'] += $v['bet'];
    }
    if ($i > 0) {
        foreach ($agentReports[$i]['teamTotalPerf'] as $k => $v) {
            if (!isset($agentReports[$i-1]['teamTotalPerf'][$k])) $agentReports[$i-1]['teamTotalPerf'][$k] = ['ggr'=>0, 'bet'=>0, 'source'=>$v['source']];
            $agentReports[$i-1]['teamTotalPerf'][$k]['ggr'] += $v['ggr'];
            $agentReports[$i-1]['teamTotalPerf'][$k]['bet'] += $v['bet'];
        }
    }
}

// 3. 计算差额明细
for ($i = 0; $i < count($agentReports) - 1; $i++) {
    $parent = &$agentReports[$i];
    $child = &$agentReports[$i+1];
    foreach ($child['teamTotalPerf'] as $k => $v) {
        $p_r = getRate($i, $v['source'], $config);
        $c_r = getRate($i+1, $v['source'], $config);
        
        $diff_zc = $p_r['zc'] - $c_r['zc'];
        $diff_fs = $p_r['fy'] - $c_r['fy'];
        $gain = (-$v['ggr'] * $diff_zc) + ($v['bet'] * $diff_fs);
        
        if ($gain != 0 || $v['ggr'] != 0) {
            $parent['subDiffDetails'][] = [
                'name' => $p_r['info']['cat'] . " / " . $p_r['info']['brand'],
                'ggr' => $v['ggr'], 'bet' => $v['bet'],
                'diff_zc' => ($diff_zc*100)."%", 'diff_fs' => ($diff_fs*100)."%",
                'gain' => $gain
            ];
            $parent['subTotalProfit'] += $gain;
            $parent['subGgr'] += $v['ggr'];
            $parent['subBet'] += $v['bet'];
        }
    }
    $parent['finalSubEarn'] = $parent['subTotalProfit'] * (1 - $parent['costRate']/100);
}

function money($val, $isGgr = false) {
    $formatted = number_format(abs($val), 2);
    if ($isGgr) {
        if ($val > 0) return "<span style='color:#d73a49; font-weight:bold;'>+" . $formatted . "</span>";
        if ($val < 0) return "<span style='color:#28a745;'>-" . $formatted . "</span>";
        return "0.00";
    }
    return ($val < 0 ? "<span style='color:red;'>-{$formatted}</span>" : $formatted);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Microsoft YaHei", sans-serif; font-size: 12px; background: #f4f7f6; padding: 20px; }
        .card { background: #fff; border: 1px solid #ccc; max-width: 1200px; margin: 0 auto 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header { background: #24292e; color: #fff; padding: 10px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #dfe2e5; padding: 8px; text-align: right; }
        th { background: #f6f8fa; text-align: center; font-size: 11px; }
        .rate-tag { font-size: 10px; color: #0366d6; display: block; font-weight: bold; }
        .cost-red { color: #d73a49; }
        .sub-detail-title { background: #f0f9eb !important; text-align: left !important; color: #67c23a; font-weight: bold; }
        .total-row { background: #fffbdd; font-weight: bold; border-top: 2px solid #f1e05a; }
        .diff-val { color: #666; font-style: italic; }
    </style>
</head>
<body>

<?php foreach ($agentReports as $lIdx => $rpt): ?>
<div class="card">
    <div class="header">结算单：<?= htmlspecialchars($rpt['name']) ?> (L<?= $lIdx ?>)</div>
    <table>
        <thead>
            <tr>
                <th style="text-align:left">直属项目明细</th>
                <th>玩家输赢(GGR)</th>
                <th>总投注</th>
                <th>占成收益</th>
                <th>返水收益</th>
                <th>游戏手续费</th>
                <th>支付手续费</th>
                <th>代付/优惠</th>
                <th>直属小计</th>
            </tr>
        </thead>
        <tbody>
            <?php $ownTotal = 0; foreach ($rpt['details'] as $d): $ownTotal += $d['profit']; ?>
            <tr>
                <td style="text-align:left; font-weight:bold;"><?= $d['name'] ?></td>
                <td><?= money($d['ggr'], true) ?></td>
                <td><?= money($d['bet']) ?></td>
                <td><?= money(-$d['ggr'] * (float)str_replace('%','',$d['rate_zc'])/100) ?><span class="rate-tag"><?= $d['rate_zc'] ?></span></td>
                <td><?= money($d['bet'] * (float)str_replace('%','',$d['rate_fs'])/100) ?><span class="rate-tag"><?= $d['rate_fs'] ?></span></td>
                <td class="cost-red">-<?= number_format($d['costs']['sx'],2) ?><span class="rate-tag"><?= $d['costs']['r_sx'] ?>%</span></td>
                <td class="cost-red">-<?= number_format($d['costs']['zf'],2) ?><span class="rate-tag"><?= $d['costs']['r_zf'] ?>%</span></td>
                <td class="cost-red">-<?= number_format($d['costs']['df'],2) ?><span class="rate-tag"><?= $d['costs']['r_df'] ?>%</span></td>
                <td style="font-weight:bold; background:#fcfcfc;"><?= money($d['profit']) ?></td>
            </tr>
            <?php endforeach; ?>

            <?php if (!empty($rpt['subDiffDetails'])): ?>
            <tr><td colspan="9" class="sub-detail-title">↓↓ 下级收益计算 (核对专用) ↓↓</td></tr>
            <tr style="background:#fcfdfd; color:#909399; font-size:11px;">
                <td style="text-align:left">下级团队项目</td>
                <td>下级总GGR</td>
                <td>下级总投注</td>
                <td>占成差点</td>
                <td>返水差点</td>
                <td colspan="3" style="text-align:center">计算过程 (GGR差额 + 投注差额)</td>
                <td>贡献收益</td>
            </tr>
            <?php foreach ($rpt['subDiffDetails'] as $sub): ?>
            <tr style="font-size:11px; color:#606266;">
                <td style="text-align:left"><?= $sub['name'] ?></td>
                <td><?= money($sub['ggr'], true) ?></td>
                <td><?= money($sub['bet']) ?></td>
                <td style="text-align:center; color:#e6a23c;"><?= $sub['diff_zc'] ?></td>
                <td style="text-align:center; color:#e6a23c;"><?= $sub['diff_fs'] ?></td>
                <td colspan="3" style="text-align:left; font-size:10px;" class="diff-val">
                    (<?= number_format(-$sub['ggr'],2) ?> × <?= $sub['diff_zc'] ?>) + (<?= number_format($sub['bet'],2) ?> × <?= $sub['diff_fs'] ?>)
                </td>
                <td style="font-weight:bold; color:#409eff;"><?= money($sub['gain']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>

            <tr style="background:#f0faff;">
                <td><?= money($ownTotal) ?></td>
            </tr>
            <?php if (isset($agentReports[$lIdx+1])): ?>
            <tr>
                <td colspan="8" style="text-align:right;">下级全线差额总计：</td>
                <td style="font-weight:bold; color:#409eff;"><?= money($rpt['subTotalProfit']) ?></td>
            </tr>
            <tr>
                <td colspan="8" style="text-align:right;">代理管理费 (扣除: <?= $rpt['costRate'] ?>%)：</td>
                <td class="cost-red">-<?= number_format($rpt['subTotalProfit'] * ($rpt['costRate']/100), 2) ?></td>
            </tr>
            <tr style="background:#f0faff;">
                <td colspan="8" style="text-align:right;">下级贡献实际所得 (扣费后)：</td>
                <td style="color:#0366d6; font-weight:bold;">+<?= money($rpt['finalSubEarn']) ?></td>
            </tr>
            <?php endif; ?>

            <tr class="total-row">
                <td colspan="8" style="text-align:right; font-size:14px;">本期应发总额 (直属+下级)：</td>
                <td style="font-size:16px; color:#28a745;"><?= money($ownTotal + $rpt['finalSubEarn']) ?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

</body>
</html>