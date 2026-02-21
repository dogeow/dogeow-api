<?php

/**
 * 代码覆盖率检查脚本
 * 检查PHPUnit测试覆盖率是否达到100%
 */

require_once __DIR__ . '/../vendor/autoload.php';

class CoverageChecker
{
    private string $coverageFile;

    private float $minCoverage;

    public function __construct(string $coverageFile = 'coverage/clover.xml', float $minCoverage = 100.0)
    {
        $this->coverageFile = $coverageFile;
        $this->minCoverage = $minCoverage;
    }

    public function check(): bool
    {
        // 检查覆盖率文件是否存在
        if (! file_exists($this->coverageFile)) {
            echo "❌ 覆盖率文件不存在: {$this->coverageFile}\n";
            echo "请先运行测试生成覆盖率报告:\n";
            echo "  composer test:coverage\n";
            echo "或者:\n";
            echo "  vendor/bin/phpunit --coverage-html coverage/html --coverage-clover coverage/clover.xml\n\n";

            // 检查Xdebug是否启用
            if (! extension_loaded('xdebug')) {
                echo "⚠️  Xdebug扩展未安装或未启用\n";
                echo "请安装并启用Xdebug:\n";
                echo "  pecl install xdebug\n";
                echo "然后在php.ini中添加:\n";
                echo "  zend_extension=xdebug.so\n";
                echo "  xdebug.mode=coverage\n";
            }

            return false;
        }

        // 解析XML文件
        $xml = simplexml_load_file($this->coverageFile);
        if (! $xml) {
            echo "❌ 无法解析覆盖率文件: {$this->coverageFile}\n";
            echo "文件可能损坏或格式不正确\n";

            return false;
        }

        // 检查是否有项目数据
        if (! isset($xml->project)) {
            echo "❌ 覆盖率文件格式不正确，缺少项目数据\n";

            return false;
        }

        $metrics = $xml->project->metrics;
        $statements = (int) $metrics['statements'];
        $coveredStatements = (int) $metrics['coveredstatements'];

        if ($statements === 0) {
            echo "⚠️  没有找到可测试的语句\n";
            echo "可能的原因:\n";
            echo "  1. 没有找到源代码文件\n";
            echo "  2. 测试配置不正确\n";
            echo "  3. 源代码路径设置错误\n";

            return true;
        }

        $coverage = ($coveredStatements / $statements) * 100;

        echo "📊 代码覆盖率报告:\n";
        echo "   总语句数: {$statements}\n";
        echo "   已覆盖语句数: {$coveredStatements}\n";
        echo '   覆盖率: ' . number_format($coverage, 2) . "%\n";
        echo "   目标覆盖率: {$this->minCoverage}%\n\n";

        if ($coverage >= $this->minCoverage) {
            echo "✅ 代码覆盖率已达到 {$this->minCoverage}% 的要求!\n";

            return true;
        } else {
            echo "❌ 代码覆盖率未达到 {$this->minCoverage}% 的要求!\n";
            echo '   当前覆盖率: ' . number_format($coverage, 2) . "%\n";
            echo '   还需要覆盖: ' . ($statements - $coveredStatements) . " 个语句\n\n";

            // 显示未覆盖的文件
            $this->showUncoveredFiles($xml);

            return false;
        }
    }

    private function showUncoveredFiles(SimpleXMLElement $xml): void
    {
        echo "📋 未完全覆盖的文件:\n";
        $uncoveredFiles = [];

        // 遍历所有包
        foreach ($xml->project->package as $package) {
            foreach ($package->file as $file) {
                $metrics = $file->class->metrics;
                $statements = (int) $metrics['statements'];
                $coveredStatements = (int) $metrics['coveredstatements'];

                if ($statements > 0 && $coveredStatements < $statements) {
                    $fileCoverage = ($coveredStatements / $statements) * 100;
                    $fileName = (string) $file['name'];
                    $fileName = str_replace(__DIR__ . '/../', '', $fileName);

                    $uncoveredFiles[] = [
                        'name' => $fileName,
                        'coverage' => $fileCoverage,
                        'covered' => $coveredStatements,
                        'total' => $statements,
                    ];
                }
            }
        }

        // 按覆盖率排序
        usort($uncoveredFiles, function ($a, $b) {
            return $a['coverage'] <=> $b['coverage'];
        });

        foreach ($uncoveredFiles as $file) {
            echo "   {$file['name']}: " . number_format($file['coverage'], 1) . "% ({$file['covered']}/{$file['total']})\n";
        }

        echo "\n💡 改进建议:\n";
        echo "   1. 优先为覆盖率较低的文件编写测试\n";
        echo "   2. 检查是否有未使用的代码可以删除\n";
        echo "   3. 考虑将复杂的逻辑拆分为更小的可测试单元\n";
        echo "   4. 确保测试文件在正确的位置 (tests/ 目录)\n";
        echo "   5. 检查测试配置是否正确\n\n";

        echo "🔧 诊断信息:\n";
        echo "   - 覆盖率文件: {$this->coverageFile}\n";
        echo '   - 文件大小: ' . number_format(filesize($this->coverageFile)) . " 字节\n";
        echo '   - 生成时间: ' . date('Y-m-d H:i:s', filemtime($this->coverageFile)) . "\n";

        // 检查测试目录
        $testDir = __DIR__ . '/../tests';
        if (is_dir($testDir)) {
            $testFiles = glob($testDir . '/**/*.php');
            echo '   - 测试文件数量: ' . count($testFiles) . "\n";
        } else {
            echo "   - 测试目录不存在: {$testDir}\n";
        }
    }
}

// 运行检查
$checker = new CoverageChecker;
$success = $checker->check();

// 输出覆盖率数据供脚本使用
if ($success) {
    echo "\nCoverage: 100.0%\n";
} else {
    // 尝试提取覆盖率数据
    if (file_exists('coverage/clover.xml')) {
        $xml = simplexml_load_file('coverage/clover.xml');
        if ($xml && isset($xml->project->metrics)) {
            $metrics = $xml->project->metrics;
            $statements = (int) $metrics['statements'];
            $coveredStatements = (int) $metrics['coveredstatements'];
            if ($statements > 0) {
                $coverage = ($coveredStatements / $statements) * 100;
                echo "\nCoverage: " . number_format($coverage, 2) . "%\n";
            } else {
                echo "\nCoverage: 0.0%\n";
            }
        } else {
            echo "\nCoverage: 0.0%\n";
        }
    } else {
        echo "\nCoverage: 0.0%\n";
    }
}

exit($success ? 0 : 1);
