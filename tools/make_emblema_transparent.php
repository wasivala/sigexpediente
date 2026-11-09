<?php
/**
 * Torna transparente o fundo branco exterior do emblema da República,
 * preservando as áreas brancas internas do brasão.
 *
 * Uso: php tools/make_emblema_transparent.php
 */

declare(strict_types=1);

$src = dirname(__DIR__) . '/assets/img/emblema-republica.png';
if (!is_file($src)) {
    fwrite(STDERR, "Emblema não encontrado: {$src}\n");
    exit(1);
}
if (!function_exists('imagecreatefrompng')) {
    fwrite(STDERR, "A extensão GD do PHP não está disponível.\n");
    exit(1);
}

$imagem = @imagecreatefrompng($src);
if (!$imagem) {
    fwrite(STDERR, "Não foi possível abrir o PNG.\n");
    exit(1);
}

$w = imagesx($imagem);
$h = imagesy($imagem);
$total = $w * $h;

$destino = imagecreatetruecolor($w, $h);
imagealphablending($destino, false);
imagesavealpha($destino, true);
$transparente = imagecolorallocatealpha($destino, 0, 0, 0, 127);
imagefilledrectangle($destino, 0, 0, $w - 1, $h - 1, $transparente);

$branch = static function (int $x, int $y) use ($imagem): array {
    $cor = imagecolorsforindex($imagem, imagecolorat($imagem, $x, $y));
    return [(int) $cor['red'], (int) $cor['green'], (int) $cor['blue']];
};

$eBranco = static function (int $r, int $g, int $b): bool {
    return $r >= 238 && $g >= 238 && $b >= 238;
};

$visitado = array_fill(0, $total, false);
$fila = [];
$inicio = 0;

$adicionar = static function (int $x, int $y) use (&$fila, &$visitado, $w, $h, $imagem, $eBranco): void {
    if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
        return;
    }
    $idx = $y * $w + $x;
    if ($visitado[$idx]) {
        return;
    }
    $cor = imagecolorsforindex($imagem, imagecolorat($imagem, $x, $y));
    if (!$eBranco((int) $cor['red'], (int) $cor['green'], (int) $cor['blue'])) {
        return;
    }
    $visitado[$idx] = true;
    $fila[] = $idx;
};

for ($x = 0; $x < $w; $x++) {
    $adicionar($x, 0);
    $adicionar($x, $h - 1);
}
for ($y = 0; $y < $h; $y++) {
    $adicionar(0, $y);
    $adicionar($w - 1, $y);
}

while ($inicio < count($fila)) {
    $idx = $fila[$inicio++];
    $x = $idx % $w;
    $y = intdiv($idx, $w);
    $adicionar($x - 1, $y);
    $adicionar($x + 1, $y);
    $adicionar($x, $y - 1);
    $adicionar($x, $y + 1);
}

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $idx = $y * $w + $x;
        if ($visitado[$idx]) {
            imagesetpixel($destino, $x, $y, $transparente);
            continue;
        }
        $cor = imagecolorsforindex($imagem, imagecolorat($imagem, $x, $y));
        $px = imagecolorallocatealpha(
            $destino,
            (int) $cor['red'],
            (int) $cor['green'],
            (int) $cor['blue'],
            (int) $cor['alpha']
        );
        imagesetpixel($destino, $x, $y, $px);
    }
}

$ok = imagepng($destino, $src, 9);
imagedestroy($imagem);
imagedestroy($destino);

if (!$ok) {
    fwrite(STDERR, "Falha ao gravar o PNG transparente.\n");
    exit(1);
}
echo "Emblema actualizado com fundo transparente: {$src}\n";
