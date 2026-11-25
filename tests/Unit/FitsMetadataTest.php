<?php

declare(strict_types=1);

it('reads FITS element values and attributes by path', function (string $path, string $expected): void {
    $xml = new SimpleXMLElement(<<<'XML'
        <fits xmlns="http://hul.harvard.edu/ois/xml/ns/fits/fits_output">
            <identification><identity mimetype="image/jpeg" /></identification>
            <fileinfo><size toolname="Jhove">80874</size><filename>351479.jpg</filename></fileinfo>
            <metadata><image>
                <imageWidth>1008</imageWidth>
                <bitsPerSample toolversion="3.6GA">8 8 8</bitsPerSample>
            </image></metadata>
        </fits>
        XML);

    expect((string) getFitsMetadataFieldValue($xml, $path))->toBe($expected);
})->with([
    'file size' => ['fileinfo.size', '80874'],
    'size tool' => ['fileinfo.size/@toolname', 'Jhove'],
    'MIME type' => ['identification.identity/@mimetype', 'image/jpeg'],
    'filename' => ['fileinfo.filename', '351479.jpg'],
    'image width' => ['metadata.image.imageWidth', '1008'],
    'bit-depth tool version' => ['metadata.image.bitsPerSample/@toolversion', '3.6GA'],
]);
