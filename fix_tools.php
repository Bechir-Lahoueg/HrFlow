<?php
$dir = __DIR__ . '/src/Service/AI/Tool';
$skip = ['ToolInterface.php','ToolRegistry.php','ToolValidator.php'];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$count = 0;
foreach ($iter as $f) {
    if ($f->getExtension() !== 'php') continue;
    if (in_array($f->getFilename(), $skip)) continue;
    $c = file_get_contents($f->getPathname());
    $c2 = preg_replace('/^(    public function getDefinition\(\): array)/m', '    /** @return array<mixed> */'."\n".'$1', $c);
    $c2 = preg_replace('/^(    public function execute\(array \$args\))/m', '    /** @param array<mixed> $args */'."\n".'$1', $c2);
    if ($c2 !== $c) {
        file_put_contents($f->getPathname(), $c2);
        $count++;
        echo "Fixed: " . $f->getFilename() . "\n";
    }
}
echo "Total modified: $count files\n";
