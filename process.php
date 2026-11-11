<?php
/**
 * ShrimpWeb /pitch — pitch-shift an audio file by semitones (ffmpeg rubberband).
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

error_reporting(E_ALL);
ini_set('display_errors', '0');
set_time_limit(300);
ini_set('memory_limit', '512M');

$ffmpegPath = '/home2/gabeisaa/ffmpeg_sources/ffmpeg-7.0.2-amd64-static/ffmpeg';
$uploadsDir = __DIR__ . '/uploads';

try {
	if (!is_dir($uploadsDir)) {
		mkdir($uploadsDir, 0755, true);
	}
	cleanup_old($uploadsDir, 6);

	if (!is_file($ffmpegPath)) {
		throw new RuntimeException('FFmpeg is not available on this server.');
	}
	if (!isset($_FILES['audioFile']) || !is_uploaded_file($_FILES['audioFile']['tmp_name'])) {
		throw new RuntimeException('No audio file uploaded.');
	}

	$file = $_FILES['audioFile'];
	if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
		throw new RuntimeException('Upload failed (code ' . (int) $file['error'] . ').');
	}
	if (($file['size'] ?? 0) > 80 * 1024 * 1024) {
		throw new RuntimeException('File is too large (max 80 MB).');
	}

	$semitones = (int) ($_POST['semitones'] ?? 0);
	if ($semitones < -12 || $semitones > 12) {
		throw new RuntimeException('Semitones must be between -12 and +12.');
	}
	if ($semitones === 0) {
		throw new RuntimeException('Choose a non-zero semitone shift.');
	}

	$preserveTempo = (($_POST['preserveTempo'] ?? '1') === '1');
	$targetFormat = strtolower((string) ($_POST['targetFormat'] ?? 'mp3'));
	$allowedFormats = ['mp3', 'wav', 'm4a', 'flac', 'ogg', 'aac'];
	if (!in_array($targetFormat, $allowedFormats, true)) {
		$targetFormat = 'mp3';
	}

	$ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
	$allowedIn = ['mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac', 'wma', 'aiff', 'aif', 'mp4', 'webm'];
	if ($ext === '' || !in_array($ext, $allowedIn, true)) {
		$ext = 'audio';
	}

	$id = bin2hex(random_bytes(6));
	$inPath = $uploadsDir . '/in_' . $id . '.' . $ext;
	if (!move_uploaded_file($file['tmp_name'], $inPath)) {
		throw new RuntimeException('Could not store the uploaded file.');
	}

	$originalBase = pathinfo((string) $file['name'], PATHINFO_FILENAME);
	$safeBase = sanitize_base($originalBase);
	$sign = $semitones > 0 ? 'up' . $semitones : 'down' . abs($semitones);
	$outName = 'shrimpweb_' . $safeBase . '_' . $sign . 'st_' . $id . '.' . $targetFormat;
	$outPath = $uploadsDir . '/' . $outName;

	// Pitch ratio: 2^(semitones/12)
	$pitch = pow(2, $semitones / 12.0);
	$pitchStr = rtrim(rtrim(sprintf('%.10F', $pitch), '0'), '.');

	if ($preserveTempo) {
		// High-quality pitch shift, duration unchanged
		$af = 'rubberband=pitch=' . $pitchStr . ':pitchq=quality';
	} else {
		// Classic resample: pitch and speed both change (chipmunk / vinyl)
		$af = 'asetrate=44100*' . $pitchStr . ',aresample=44100';
	}

	$formatArgs = format_args($targetFormat);

	$cmd = escapeshellarg($ffmpegPath)
		. ' -y -i ' . escapeshellarg($inPath)
		. ' -af ' . escapeshellarg($af)
		. ' ' . $formatArgs
		. ' ' . escapeshellarg($outPath)
		. ' 2>&1';

	exec($cmd, $output, $code);
	@unlink($inPath);

	if ($code !== 0 || !is_file($outPath) || filesize($outPath) === 0) {
		if (is_file($outPath)) {
			@unlink($outPath);
		}
		$detail = '';
		foreach ($output as $line) {
			if (stripos($line, 'error') !== false) {
				$detail .= ' ' . $line;
			}
		}
		throw new RuntimeException('Pitch shift failed.' . ($detail ? ' ' . trim($detail) : ''));
	}

	echo json_encode([
		'success'     => true,
		'message'     => 'Done',
		'downloadUrl' => 'uploads/' . rawurlencode($outName),
		'filename'    => $outName,
		'size'        => filesize($outPath),
		'semitones'   => $semitones,
	]);
} catch (Throwable $e) {
	http_response_code(400);
	echo json_encode([
		'success' => false,
		'message' => $e->getMessage(),
	]);
}

function sanitize_base(string $name): string {
	$safe = strtr($name, [
		'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
		'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
		'ß' => 'ss',
	]);
	$safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $safe) ?? '';
	$safe = trim($safe, '_');
	if ($safe === '') {
		$safe = 'audio';
	}
	return substr($safe, 0, 60);
}

function format_args(string $fmt): string {
	switch ($fmt) {
		case 'wav':
			return '-c:a pcm_s16le';
		case 'flac':
			return '-c:a flac -compression_level 5';
		case 'ogg':
			return '-c:a libvorbis -q:a 5';
		case 'm4a':
		case 'aac':
			return '-c:a aac -b:a 192k';
		case 'mp3':
		default:
			return '-c:a libmp3lame -b:a 192k -q:a 2';
	}
}

function cleanup_old(string $dir, int $hours): void {
	$cutoff = time() - ($hours * 3600);
	foreach (glob($dir . '/*') ?: [] as $file) {
		if (!is_file($file)) {
			continue;
		}
		if (basename($file) === '.htaccess') {
			continue;
		}
		if (filemtime($file) < $cutoff) {
			@unlink($file);
		}
	}
}
