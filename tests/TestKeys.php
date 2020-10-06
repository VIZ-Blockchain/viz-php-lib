<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include('./class/autoloader.php');

final class TestKeys extends TestCase
{
    public function testPublicFromPrivateKey(): void
    {
        $private_key = new VIZ\Key('b9f3c242e5872ac828cf2ef411f4c7b2a710bd9643544d735cc115ee939b3aae');
        $public_key = $private_key->get_public_key()->encode();
        $this->assertEquals(
            $public_key,
            'VIZ5MY1yEaHaJ9i6W2UWFH57Kscr7aRgz9HksQy9QQWMhUvUJWXn4'
        );
    }

    public function testVerifySignature(): void
    {
        $private_key = new VIZ\Key('b9f3c242e5872ac828cf2ef411f4c7b2a710bd9643544d735cc115ee939b3aae');
        $data = 'Hello VIZ.World!';
        $signature = $private_key->sign($data);
        $public_key = $private_key->get_public_key();
        $this->assertTrue($public_key->verify($data, $signature));
        $this->assertFalse($public_key->verify('Bye VIZ.World!', $signature));
    }
}
