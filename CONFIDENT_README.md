
# WikibaseCirrusSearch Patches and Indexing Guide

This document describes how to maintain the custom `WikibaseCirrusSearch` index fields used by the Everyday Jazz Wikibase deployment.

It covers:

- adding a custom index field;
- making sure changes are included in the Docker image and available to both Wikibase and the jobrunner;
- updating the field-definition registry and the ConfIDent search services that consume indexed fields;
- checking a document and reindexing one page or the full wiki.

> **Important:** Do not treat edits made directly inside a running container as the source of truth. A container can contain uncommitted changes in its writable layer; those changes disappear when the container is recreated. Keep the patch source files in `/root/wikibase-release-pipeline/deploy/config/patches/` and build them into `wikibase-custom:5`.

## 1. Paths and deployment layout

On the Docker host:

| Purpose | Path / name |
|---|---|
| Release-pipeline repository | `/root/wikibase-release-pipeline` |
| Compose working directory | `/root/wikibase-release-pipeline/deploy` |
| Compose file | `deploy/docker-compose.yml` |
| Custom Dockerfile | `deploy/config/Dockerfile.wikibase` |
| Patch sources | `deploy/config/patches/` |
| Custom image | `wikibase-custom:5` |
| Wikibase container | `wbs-deploy-wikibase-1` |
| Jobrunner container | `wbs-deploy-wikibase-jobrunner-1` |
| Main custom field definitions | `WikibaseCirrusSearch/src/Fields/StatementProviderFieldDefinitions.php` |
| Entity-type field registration | `WikibaseCirrusSearch/WikibaseSearch.entitytypes.php` |
| Existing `statement_keywords` patch | `deploy/config/patches/StatementsField.php` |

The Compose configuration builds **both** `wikibase` and `wikibase-jobrunner` from `config/Dockerfile.wikibase` and tags the result as `wikibase-custom:5`. The two services must run the same custom indexing code.

The `Confident` extension is mounted from the host directory `deploy/config/extensions` into `/var/www/html/extensions/extensions`. Recreating the containers does not delete that host directory. Keep changes to `Confident` in that host directory rather than only editing the live container.

## 2. Adding a new index field

### Step 1 — Create the field class

Create the class under the patch source directory, for example:

```text
deploy/config/patches/WikibaseCirrusSearch/src/Fields/MyNewIndexField.php
```

Use the namespace expected by the existing classes:

```php
namespace Wikibase\Search\Elastic\Fields;
```

Choose the appropriate base class by looking at similar existing fields:

- `AbstractEventStatementsIndexField` for fields derived from an entity's statements;
- `AbstractEventLinkedEntityIndexField` for fields that resolve data from linked entities;
- `ConfiguredEventScalarIndexField` for simple configured scalar values where its existing behavior fits.

Do not choose a base class only because its name sounds similar. Read its constructor and `getFieldData()` implementation and follow the pattern used by neighboring field classes.

Implement the expected field name, data extraction, and Elasticsearch mapping. The mapping must match the shape returned by `getFieldData()` (for example, a scalar, object, or nested array). A mapping/data-shape mismatch can cause queries to fail or fields to be unusable.

### Step 2 — Register the field

Creating a PHP class does **not** automatically make the field part of the indexed document. Register it in:

```text
WikibaseCirrusSearch/src/Fields/StatementProviderFieldDefinitions.php
```

Follow the existing pattern. For example, field definitions are registered using the field name and an instance of the class. The exact constructor arguments depend on the field type.

Also inspect:

```text
WikibaseCirrusSearch/WikibaseSearch.entitytypes.php
```

This file connects the field-definition provider to Wikibase entity types. Usually a new field added to the existing `StatementProviderFieldDefinitions` provider does not require a new entity-type registration, but if you create a separate provider or change which entity types are indexed, this registration must be reviewed too.

If the field relies on a new property ID or configuration key, update the appropriate ConfIDent property/configuration class as well. Do not hard-code property IDs in several unrelated places if the project already has a central configuration class.

### Step 3 — Put the source file in the patch directory

The custom Dockerfile copies the following directory into the image:

```text
deploy/config/patches/WikibaseCirrusSearch/src/Fields/
```

Therefore, a new field class placed in that directory is included by the existing directory-level `COPY` statement. Verify that the new file exists there before building.

If you add a helper class outside `src/Fields/`, or modify another upstream file, add a corresponding patch file and a `COPY` instruction to `deploy/config/Dockerfile.wikibase`. Do not assume that a new file created only inside the running container will be included in the next image.

## 3. Docker image: include and deploy changes

The custom Dockerfile is:

```text
deploy/config/Dockerfile.wikibase
```

Its key copy operations should preserve the existing `StatementsField.php` implementation and include the custom field classes and registry file. The relevant pattern is:

```dockerfile
FROM wikibase/wikibase:5

# Preserve the customized statement index implementation.
COPY config/patches/StatementsField.php /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/StatementsField.php

# Install custom index field classes.
COPY config/patches/WikibaseCirrusSearch/src/Fields/ /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/

# Register the custom field definitions.
COPY config/patches/WikibaseCirrusSearch/WikibaseSearch.entitytypes.php /var/www/html/extensions/WikibaseCirrusSearch/WikibaseSearch.entitytypes.php

RUN chown -R www-data:www-data /var/www/html/extensions/WikibaseCirrusSearch
```

If your checked-in Dockerfile has more steps, preserve them; do not replace unrelated configuration. Keep `deploy/config/patches/StatementsField.php` as the canonical source for that customized file.

### Build the image for Wikibase & Wikibase-Jobrunner

Run these commands on the Docker host:


```bash
cd /root/wikibase-release-pipeline/deploy

docker compose build --no-cache wikibase wikibase-jobrunner
```

Affected containers:

* wikibase
* wikibase-jobrunner

The build should complete successfully before continuing.

### Check the image before recreating containers

Verify that the image contains all relevant files:

```bash
docker run --rm --entrypoint bash wikibase-custom:5 -lc '
set -e
for f in \
  /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/StatementsField.php \
  /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/TitlesIndexField.php \
  /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/ConfiguredEventScalarIndexField.php \
  /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/StatementProviderFieldDefinitions.php \
  /var/www/html/extensions/WikibaseCirrusSearch/WikibaseSearch.entitytypes.php
do
  test -f "$f" || { echo "MISSING: $f"; exit 1; }
  sha256sum "$f"
done
'
```

For the customized `StatementsField.php`, confirm `getFieldData()` returns `$data`, not an empty array. A `return [];` inside `getMapping()` for a non-CirrusSearch engine is a different branch and is not the same issue.

### Recreate both services

After the image checks pass:

```bash
docker compose up -d --no-deps --force-recreate wikibase wikibase-jobrunner
docker compose ps
```

Recreating both services is important: normal indexing jobs are processed by the jobrunner, so updating only the web container can leave the indexer running stale code.

### Verify that both containers have identical files

```bash
for c in wbs-deploy-wikibase-1 wbs-deploy-wikibase-jobrunner-1; do
  echo "===== $c ====="
  docker exec "$c" php -v | head -n 1
  docker exec "$c" sha256sum \
    /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/StatementsField.php \
    /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/TitlesIndexField.php \
    /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/ConfiguredEventScalarIndexField.php \
    /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/StatementProviderFieldDefinitions.php \
    /var/www/html/extensions/WikibaseCirrusSearch/WikibaseSearch.entitytypes.php
done
```

Compare hashes line by line. Both containers should report the same hash for each file. In the current deployment, PHP is expected to be `8.3.23`; if the base image changes, check the actual version rather than assuming it.

**Avoid editing only the running container.** `docker diff wbs-deploy-wikibase-1` can reveal files added or changed in the writable container layer. Such changes should be moved into the patch sources and Dockerfile before future recreations.

## 4. What must be updated in the search services?

Think of indexing as three separate layers. Changing only one layer is often insufficient.

### A. Index-field implementation and registration

Review these files when you add or change an indexed field:

1. The field class, usually under `WikibaseCirrusSearch/src/Fields/`.
2. `StatementProviderFieldDefinitions.php`, where field instances are registered.
3. `WikibaseSearch.entitytypes.php`, if the field provider or entity-type registration changes.
4. Any central configuration/helper class used to resolve property IDs or related entity values.

### B. ConfIDent API/search query services

Making a field available in Elasticsearch does not automatically make the search API use it. Review the query builder/service and result mapping for every endpoint that should read the field. In this deployment, inspect the custom `Confident` extension, including search/suggest endpoints such as `Suggest`, `SuggestOrganization`, `SuggestLocation`, and the custom `confidentsearch` path where applicable.

Look for:

- query filters and full-text/match conditions;
- nested query structure if the mapping is `nested`;
- field lists and boosts;
- sorting or date filters;
- result/DTO extraction from the Elasticsearch hit;
- fallback logic that reads an older field such as `statement_keywords`.

Useful host-side search command:

```bash
cd /root/wikibase-release-pipeline
grep -RIn --include='*.php' \
  -E 'statement_keywords|titles|updated_at|instance_of|part_of_series|maintainer|record_status' \
  deploy/config/extensions/Confident
```

Adapt the search terms to the field being changed. Update only the services that should consume the field; preserve compatibility for consumers still using `statement_keywords` until they have been migrated deliberately.

### C. Entity data and semantics

Verify that the source entity actually contains the value you expect and that the index field is intended to represent that value. For example, a title statement (P21) is not necessarily the same thing as the Wikibase label (`labels.en`). Changing P21 does not automatically guarantee that the label changes.

Similarly, an `updated_at` index field reflects the configured source property/value. Changing an unrelated property does not necessarily update the source `updated_at` statement; make sure the write/update service sets that property if that is the intended application behavior.

## 5. Validate a document before reindexing

Use a MediaWiki **page ID** for the CirrusSearch maintenance command, not the Wikibase entity ID such as `Q85958`. In the example below, `Q85958` has MediaWiki page ID `83600`.

Fetch the document that would be generated from the current entity:

```bash
docker exec wbs-deploy-wikibase-1 bash -lc '
curl -fsS \
  "http://127.0.0.1/api.php?action=query&prop=cirrusbuilddoc&pageids=83600&format=json&formatversion=2" \
  > /tmp/Q85958-builddoc.json
python3 - <<"PY"
import json
with open("/tmp/Q85958-builddoc.json", encoding="utf-8") as f:
    data = json.load(f)
doc = data["query"]["pages"][0]["cirrusbuilddoc"]
for key in ("timestamp", "labels", "titles", "updated_at", "statement_keywords"):
    value = doc.get(key)
    print(f"\\n{key}:")
    if key == "statement_keywords" and isinstance(value, list):
        print("Row count:", len(value))
        print(json.dumps(value[:5], ensure_ascii=False, indent=2))
    else:
        print(json.dumps(value, ensure_ascii=False, indent=2))
PY
'
```

Check the currently indexed document separately:

```bash
docker exec wbs-deploy-wikibase-1 bash -lc '
curl -fsS \
  "http://127.0.0.1/api.php?action=query&prop=cirrusdoc&pageids=83600&format=json&formatversion=2" \
  > /tmp/Q85958-cirrusdoc.json
python3 - <<"PY"
import json
with open("/tmp/Q85958-cirrusdoc.json", encoding="utf-8") as f:
    data = json.load(f)
doc = data["query"]["pages"][0]["cirrusdoc"][0]["source"]
for key in ("timestamp", "labels", "titles", "updated_at", "statement_keywords"):
    value = doc.get(key)
    print(f"\\n{key}:")
    if key == "statement_keywords" and isinstance(value, list):
        print("Row count:", len(value))
        print(json.dumps(value[:5], ensure_ascii=False, indent=2))
    else:
        print(json.dumps(value, ensure_ascii=False, indent=2))
PY
'
```

Interpret the comparison:

- **`cirrusbuilddoc` is current but `cirrusdoc` is old:** the index document has not been updated with the generated value; investigate the normal indexing/jobrunner/write path.
- **Both are old:** investigate entity data, field implementation, field registration, configuration, or cached/runtime code.
- **Both are current but search returns old/no result:** inspect the query field, filters, sorting, alias/index, and the API response mapping.

Remember that `labels` and the custom `titles` statement field have different semantics and may legitimately contain different values.

## 6. Reindex one page

After the build document is correct and `statement_keywords` contains the expected rows, reindex one page first. For the current example:

```bash
docker exec wbs-deploy-wikibase-1 php maintenance/run.php CirrusSearch:ForceSearchIndex --ids 83600 --batch-size 1
```

Then fetch `cirrusdoc` again and compare it with `cirrusbuilddoc`. Check at least:

- the new `titles` value;
- `updated_at` when its source value is expected to change;
- a non-empty `statement_keywords` array and the expected property values;
- other custom fields affected by the change.

A forced reindex rebuilds the document from source data. It can therefore remove a legacy field if the current `getFieldData()` implementation returns an empty array. Do not proceed until the generated document has been checked.

## 7. Full reindexing

Use full reindexing only when the scope of the change requires it and after testing one page. It can create substantial load on MediaWiki and Elasticsearch and may make search temporarily incomplete, depending on the index/configuration procedure.

For a normal full source-document reindex, the standard maintenance command is:

```bash
docker exec wbs-deploy-wikibase-1 php maintenance/run.php CirrusSearch:ForceSearchIndex
```

If you changed Elasticsearch mappings or index configuration (not just the values generated by an existing mapping), assess whether the index configuration must be updated first. CirrusSearch documents workflows involving `CirrusSearch:UpdateSearchIndexConfig` and `CirrusSearch:ForceSearchIndex`; options such as `--startOver` can recreate the index and should not be used casually. Review the README shipped with the installed CirrusSearch version and plan a maintenance window before changing the index configuration.

Official references:

- [CirrusSearch extension manual](https://www.mediawiki.org/wiki/Extension:CirrusSearch)
- [CirrusSearch README](https://github.com/wikimedia/mediawiki-extensions-CirrusSearch/blob/master/README)

## 8. Job queue versus forced reindex

These commands serve different purposes:

```bash
# Process jobs that are already queued (this does not itself guarantee a full reindex)
docker exec wbs-deploy-wikibase-1 php maintenance/run.php runJobs --maxjobs=50 --maxtime=60 --result=json

# Rebuild a specific page from source data
docker exec wbs-deploy-wikibase-1 php maintenance/run.php CirrusSearch:ForceSearchIndex --ids 83600 --batch-size 1
```

If `runJobs` reports no ready jobs, that does not prove that an existing stale Elasticsearch document has been fixed. Use `ForceSearchIndex` when you intentionally need to rebuild a document.

## 9. Troubleshooting checklist

When a custom field is stale or missing, check in this order:

1. Is the source value present on the Wikibase entity?
2. Does `cirrusbuilddoc` contain the expected value?
3. Is the field class registered by `StatementProviderFieldDefinitions.php` and reachable through `WikibaseSearch.entitytypes.php`?
4. Are the patch files present in `deploy/config/patches/` and copied into the custom image?
5. Were both `wikibase` and `wikibase-jobrunner` rebuilt/recreated from the same image?
6. Do the two containers return identical hashes for the relevant files?
7. Does `cirrusdoc` match the `cirrusbuilddoc` output after a single-page reindex?
8. Does the search service actually query the field and return/map it correctly?
9. If only one field remains stale, inspect the field's data/mapping implementation and the update/write logs before running a full reindex.

Quick hash check:

```bash
for c in wbs-deploy-wikibase-1 wbs-deploy-wikibase-jobrunner-1; do
  echo "===== $c ====="
  docker exec "$c" sha256sum \
    /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/StatementsField.php \
    /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/TitlesIndexField.php \
    /var/www/html/extensions/WikibaseCirrusSearch/src/Fields/StatementProviderFieldDefinitions.php \
    /var/www/html/extensions/WikibaseCirrusSearch/WikibaseSearch.entitytypes.php
done
```

## 10. Before committing a patch

- [ ] New field class is stored in the patch source directory.
- [ ] Field is registered in the provider.
- [ ] Entity-type field registration has been reviewed.
- [ ] Any relevant ConfIDent query/result services have been updated.
- [ ] `StatementsField.php` still returns `$data` from `getFieldData()` and preserves existing indexed statement rows.
- [ ] Docker image builds successfully.
- [ ] Both services were recreated from the same image.
- [ ] Hashes match in both containers.
- [ ] `cirrusbuilddoc` has the expected value and `statement_keywords` is not unexpectedly empty.
- [ ] One-page reindex and a search query have been verified before any full reindex.
