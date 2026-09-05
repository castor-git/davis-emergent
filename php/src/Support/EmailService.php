<?php
namespace App\Support;

/**
 * Minimal Resend/Emergent-managed email client.
 * Follows the Emergent Resend playbook (X-Email-Key header, from_name required,
 * fixed EMAIL_BASE_URL). Recipients + HTML come from server-side templates only.
 */
class EmailService {
    // Hardcoded per the Emergent playbook — must NOT come from env.
    private const BASE_URL = 'https://integrations.emergentagent.com';

    public static function send(string $to, string $subject, string $html, ?string $replyTo = null): array {
        $key = getenv('EMERGENT_EMAIL_KEY') ?: '';
        $fromName = getenv('EMAIL_FROM_NAME') ?: 'DAVISPORN';
        if (!$key) return ['ok'=>false,'skipped'=>true,'reason'=>'EMERGENT_EMAIL_KEY missing'];

        self::assertSafe($subject, $html);
        $payload = ['to'=>[$to], 'subject'=>$subject, 'html'=>$html, 'from_name'=>$fromName];
        $r = $replyTo ?: (getenv('EMAIL_REPLY_TO') ?: '');
        if ($r) $payload['contact_email'] = $r;

        $ch = curl_init(self::BASE_URL . '/api/v1/email/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Email-Key: ' . $key],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok'=>($code>=200 && $code<300), 'status'=>$code, 'body'=>$body, 'error'=>$err];
    }

    /** Structural G2/G3 gate. Rewrite copy if it trips — never weaken. */
    private static function assertSafe(string $subject, string $html): void {
        if (preg_match('/<\s*(form|input|textarea|select)\b/i', $html)) {
            throw new \RuntimeException('Email must not contain form/input (G2)');
        }
        $body = strtolower($subject . "\n" . $html);
        $askPhrases = ['reply with your password','reply with the code','send your password','cvv','send us your password','enter your password below','confirm your card number','your full card number','seed phrase','recovery phrase','verify your card','social security number','confirm your bank details'];
        foreach ($askPhrases as $p) if (str_contains($body, $p)) throw new \RuntimeException('Email asks for credentials (G2): ' . $p);

        preg_match_all('/\b(?:href|src)\s*=\s*"([^"]*)"/i', $html, $m);
        $shorteners = ['bit.ly','tinyurl.com','t.co','is.gd','cutt.ly','goo.gl','rebrand.ly'];
        foreach ($m[1] as $url) {
            $u = strtolower(trim($url));
            if ($u === '' || preg_match('/^(mailto:|tel:|cid:|#)/', $u)) continue;
            if (!str_starts_with($u, 'https://')) throw new \RuntimeException("Non-https URL: {$url} (G3)");
            $p = parse_url($u);
            $host = $p['host'] ?? '';
            if ($host === '' || str_contains($host, 'xn--') || filter_var($host, FILTER_VALIDATE_IP)) {
                throw new \RuntimeException("Bad host: {$host} (G3)");
            }
            foreach ($shorteners as $s) if ($host === $s || str_ends_with($host, '.' . $s)) {
                throw new \RuntimeException("Shortener URL: {$host} (G3)");
            }
            if (!empty($p['user'])) throw new \RuntimeException("Credential-bearing URL (G3)");
        }
    }
}
