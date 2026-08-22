<?php

use App\Services\Google\GoogleDriveUrlParser;

it('extracts file id from standard drive url', function () {
    $parser = new GoogleDriveUrlParser;

    $result = $parser->parse('https://drive.google.com/file/d/1AbC-123_XYZ/view?usp=sharing');

    expect($result['file_id'])->toBe('1AbC-123_XYZ');
});

it('extracts file id from open url', function () {
    $parser = new GoogleDriveUrlParser;

    $result = $parser->parse('https://drive.google.com/open?id=abc123FileId');

    expect($result['file_id'])->toBe('abc123FileId');
});

it('rejects non google urls', function () {
    $parser = new GoogleDriveUrlParser;

    $parser->parse('https://example.com/video.mp4');
})->throws(InvalidArgumentException::class);

it('rejects malformed drive urls', function () {
    $parser = new GoogleDriveUrlParser;

    $parser->parse('https://drive.google.com/drive/folders/abc123');
})->throws(InvalidArgumentException::class);
