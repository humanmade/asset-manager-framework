# MultilingualPress (MLP) Integration

This is an integration later between AMF and [MultilingualPress 3](https://multilingualpress.org/). MLP 2 and earlier have not been tested.

## Issues

This integration addresses the following issues:

1. **Featured images do not synchronise to the translation** ([#3](https://github.com/humanmade/asset-manager-framework/issues/3))

	The featured image doesn't get synced when copying a post from the source to the translation and choosing the *Advanced → Copy featured image* option within MLP.

	The reason the sync fails is because MLP performs checks to ensure the attachment file exists before syncing it, and bails out if not. This is expected behaviour.

	This integration layer syncs the attachment and its metadata whenever the *Copy featured image* option is checked, without performing any checks for the validity of the attachment file.

## Documentation

* https://multilingualpress.org/docs/copy-post-meta/
* https://multilingualpress.org/docs/run-code-copy-source-content-option-checked/
