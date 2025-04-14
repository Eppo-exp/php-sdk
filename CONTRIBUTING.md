# Releases

When creating a release, **do not apply a tag**. Name the release as normal and publish it. The
workflow `.github/workflows/publish-release.yml`
will run tests and then apply a tag matching the release name. This tag will be picked up automatically by Packagist
which will then publish a new release.
