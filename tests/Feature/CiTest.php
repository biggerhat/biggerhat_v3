<?php

use App\Support\Release;

it('reads the running commit from the checkout: a branch ref, a packed ref and a detached head', function () {
    $sha = str_repeat('a1', 20);
    $dir = sys_get_temp_dir().'/release-'.bin2hex(random_bytes(4));
    mkdir($dir.'/refs/heads', 0777, true);

    file_put_contents($dir.'/HEAD', "ref: refs/heads/main\n");
    file_put_contents($dir.'/refs/heads/main', $sha."\n");
    expect(Release::sha($dir))->toBe($sha);

    // after a gc the ref is only in packed-refs
    unlink($dir.'/refs/heads/main');
    $packed = str_repeat('b2', 20);
    file_put_contents($dir.'/packed-refs', "# pack-refs\n{$packed} refs/heads/main\n");
    expect(Release::sha($dir))->toBe($packed);

    file_put_contents($dir.'/HEAD', $sha."\n");
    expect(Release::sha($dir))->toBe($sha);
});

it('says nothing when there is no checkout, and ignores a head that points outside refs/', function () {
    expect(Release::sha('/nonexistent/path'))->toBeNull();

    $dir = sys_get_temp_dir().'/release-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/HEAD', "ref: ../../etc/passwd\n");
    expect(Release::sha($dir))->toBeNull();
});

it('serves the version without a session or a cookie, and never cached', function () {
    $response = $this->getJson(route('version'))->assertOk()->assertJsonStructure(['sha']);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->headers->getCookies())->toBe([]);
});
