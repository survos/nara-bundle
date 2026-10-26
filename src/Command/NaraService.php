<?php

declare(strict_types=1);

namespace Survos\NaraBundle\Command;

use Survos\Nara\Client;
use Survos\Nara\SearchQuery;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

final class NaraService
{
    private const CONTRIBUTION_TYPES = ['transcription', 'tag', 'comment'];

    public function __construct(private readonly Client $client)
    {
    }

    #[AsCommand('nara:search', 'Search the National Archives Catalog')]
    public function search(
        SymfonyStyle $io,
        #[Argument('Search terms (or a numeric NA ID)')]
        string $query = '*',
        #[Option('Maximum hits to fetch')]
        int $limit = 10,
        #[Option('Record group number')]
        ?string $recordGroup = null,
        #[Option('Digital object type keyword(s), comma-separated (e.g. pdf)')]
        ?string $objectType = null,
        #[Option('Only records with digital objects online')]
        bool $online = false,
        #[Option('Only records that already have a transcription')]
        bool $transcribed = false,
        #[Option('Print the raw hits as JSON')]
        bool $json = false,
    ): int {
        $search = new SearchQuery(
            q: ctype_digit($query) ? null : $query,
            naId: ctype_digit($query) ? $query : null,
            recordGroup: $recordGroup,
            availableOnline: $online,
            objectType: $objectType,
            transcriptionsExist: $transcribed,
            limit: min($limit, 100),
        );

        $hits = iterator_to_array($this->client->iterate($search, $limit), false);
        if ($json) {
            $io->writeln(json_encode($hits, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($hits as $hit) {
            $record = $hit['_source']['record'] ?? [];
            $rows[] = [$record['naId'] ?? $hit['_id'] ?? '', $record['levelOfDescription'] ?? '', \count($record['digitalObjects'] ?? []), mb_strimwidth((string) ($record['title'] ?? ''), 0, 80, '…')];
        }
        $io->table(['naId', 'level', 'objects', 'title'], $rows);

        return Command::SUCCESS;
    }

    #[AsCommand('nara:record', 'Show one record and its digital objects')]
    public function record(
        SymfonyStyle $io,
        #[Argument('NA ID')]
        int $naId,
        #[Option('Print the raw API payload as JSON')]
        bool $json = false,
    ): int {
        if ($json) {
            $io->writeln(json_encode($this->client->getRecordPayload($naId), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $record = $this->client->getRecordByNaId($naId);
        if (null === $record) {
            $io->error(\sprintf('No record %d.', $naId));

            return Command::FAILURE;
        }

        $io->title(\sprintf('%s — %s', $record->naId, $record->title));
        $io->table(['objectId', 'type', 'file', 'url'], array_map(
            static fn ($o): array => [$o->objectId, $o->objectType, $o->objectFilename, $o->objectUrl],
            $record->digitalObjects,
        ));

        return Command::SUCCESS;
    }

    #[AsCommand('nara:contribute', 'Submit a transcription, tag or comment to the Catalog (dry-run unless --live)')]
    public function contribute(
        SymfonyStyle $io,
        #[Argument('transcription, tag or comment')]
        string $type,
        #[Argument('NA ID of the record')]
        int $naId,
        #[Option('Digital object ID the transcription belongs to (required for transcriptions)')]
        int $objectId = 0,
        #[Option('The text to submit')]
        ?string $text = null,
        #[Option('Read the text from this file instead')]
        ?string $textFile = null,
        #[Option('Actually write to the live Catalog')]
        bool $live = false,
    ): int {
        if (!\in_array($type, self::CONTRIBUTION_TYPES, true)) {
            $io->error('Type must be one of: '.implode(', ', self::CONTRIBUTION_TYPES));

            return Command::INVALID;
        }
        if ('transcription' === $type && $objectId <= 0) {
            $io->error('Transcriptions require --object-id (the page the text belongs to).');

            return Command::INVALID;
        }
        $userId = $this->client->getUserId();
        if (null === $userId) {
            $io->error('NARA_USER_ID is not set; contributions must be attributed to a Catalog user.');

            return Command::FAILURE;
        }

        if (null !== $textFile) {
            $text = is_file($textFile) ? (string) file_get_contents($textFile) : null;
        } elseif (null === $text && !stream_isatty(\STDIN)) {
            $text = (string) stream_get_contents(\STDIN);
        }
        if (null === $text || '' === trim($text)) {
            $io->error('No text: use --text, --text-file, or pipe it on STDIN.');

            return Command::FAILURE;
        }

        $io->writeln(\sprintf('%s naId=%d%s user=%s, %d chars: %s', strtoupper($type), $naId, $objectId > 0 ? ' object='.$objectId : '', $userId, \strlen($text), mb_strimwidth(str_replace("\n", ' ', $text), 0, 100, '…')));
        if (!$live) {
            $io->note('Dry-run: nothing sent. Re-run with --live to write to the National Archives Catalog.');

            return Command::SUCCESS;
        }

        $result = match ($type) {
            'transcription' => $this->client->submitTranscription($naId, $text, $objectId),
            'tag' => $this->client->submitTag($naId, $text),
            'comment' => $this->client->submitComment($naId, $text),
        };
        if (!$result->success) {
            $io->error(\sprintf('HTTP %d: %s', $result->statusCode, $result->message ?? ''));

            return Command::FAILURE;
        }
        $io->success(\sprintf('Saved (HTTP %d) contributionId=%s', $result->statusCode, $result->contributionId ?? '?'));

        return Command::SUCCESS;
    }
}
