# survos/nara-bundle

Symfony integration for [`survos/nara-client`](../../lib/nara-client) (National Archives Catalog API v2), built on `survos/kit-bundle`.

```bash
composer require survos/nara-bundle
```

Set the environment variables; no config file is needed:

```dotenv
NARA_API_KEY=...            # email Catalog_API@nara.gov
NARA_USER_ID=...            # Catalog user UUID; only needed to contribute
NARA_SUPPORT_EMAIL=Catalog_API@nara.gov   # optional: sent as From / CC so NARA can reach heavy users
NARA_SUPPORT_EMAIL_CC=DASNACSupport@nara.gov
```

To use other variable names, or literal values:

```yaml
# config/packages/survos_nara.yaml
survos_nara:
    api_key: '%env(MY_NARA_KEY)%'
```

Inject `Survos\Nara\Client` anywhere (see the client README for the API). It retries 429/5xx with backoff.

## Commands

```bash
bin/console nara:search "widow pension" --record-group=15 --object-type=pdf --online --limit=50
bin/console nara:record 170821856 [--json]
# dry-run unless --live; text from --text, --text-file or STDIN
bin/console nara:contribute transcription 170821856 --object-id=170821859 --text-file=page1.txt [--live]
```
