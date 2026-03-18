<?php
// 确保存放数据的目录存在
if (!is_dir('data')) {
    mkdir('data', 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawConfig = $_POST['cfg'] ?? [];
    $finalConfig = [];

    foreach ($rawConfig as $cat) {
        // 1. 跳过没有名称的分类
        if (empty($cat['name'])) continue;

        // 2. 获取分类层费率
        $catRates = isset($cat['rates']) ? $cat['rates'] : array_fill(0, 12, "0");

        // 3. 解析品牌数据 (前端是通过 JSON.stringify 存入 textarea 的)
        $brandsData = isset($cat['brands']) ? json_decode($cat['brands'], true) : [];
        
        $processedBrands = [];
        if (is_array($brandsData)) {
            foreach ($brandsData as $brand) {
                // 确保品牌下属的游戏数据也被带入
                $processedBrands[] = [
                    'brand_name' => $brand['name'] ?? '',
                    'brand_rates' => $brand['rates'] ?? array_fill(0, 12, "0"),
                    'games' => $brand['games'] ?? [] // 这里的 games 应该已经是带费率的数组了
                ];
            }
        }
        
        // 4. 组装最终结构
        $finalConfig[] = [
            'category_name'  => $cat['name'],
            'category_rates' => $catRates,
            'brands'         => $processedBrands
        ];
    }

    // 5. 写入 JSON
    $jsonContent = json_encode($finalConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
  if (file_put_contents('data/config.json', $jsonContent)) {
    // 修改这里：保存后直接跳转到第二步
    echo "<script>alert('配置已成功保存！现在进入第二步：上传账单报表'); window.location.href='step2.php';</script>";
} else {
    echo "保存失败，请检查 data 目录权限";
}
}
?>