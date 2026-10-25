<?php
declare(strict_types=1);
// Read-only contract test: no DB and no HTTP calls.
require_once dirname(__DIR__).'/vendor/autoload.php';
$reflection = new ReflectionClass(App\Service\ProfileExternalReviewService::class);
$service = $reflection->newInstanceWithoutConstructor();
$method = $reflection->getMethod('documentaryDisplayProjection');
$payload = [
    'schema'=>'ezstudio.assistant.catalogue-suggestions.v1',
    'work'=>[
        'full_title'=>['value'=>'Aline','status'=>'candidate',
                       'sources'=>['https://musicbrainz.org/work/example']],
        'lyricists'=>[['value'=>'Auteur exemple','status'=>'candidate',
                       'sources'=>['https://musicbrainz.org/work/test']]],
        'composers'=>[],
    ],
    'recording'=>['performers'=>[['value'=>'Christophe','status'=>'candidate',
                     'sources'=>['https://musicbrainz.org/recording/test']]],
                  'arrangers'=>[]],
    'consulted_identity_sources'=>['https://musicbrainz.org/recording/test','javascript:alert(1)'],
];
$result = $method->invoke($service, $payload);
if (!is_array($result) || $result['work']['full_title']['value'] !== 'Aline'
    || count($result['work']['lyricists']) !== 1
    || $result['recording']['performers'][0]['value'] !== 'Christophe'
    || count($result['consulted_identity_sources']) !== 1
    || $result['identity_verified'] !== false) {
    fwrite(STDERR, "PROFILE_DOCUMENTARY_DISPLAY_CONTRACT_FAILED\n");
    exit(1);
}
if ($method->invoke($service, ['schema'=>'wrong']) !== null) {
    fwrite(STDERR, "PROFILE_DOCUMENTARY_UNEXPECTED_SCHEMA\n");
    exit(1);
}
echo "PROFILE_DOCUMENTARY_DISPLAY_CONTRACT_OK\n";
