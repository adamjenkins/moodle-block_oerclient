# Release notes — 1.0.0

The first stable release. The block is declared `MATURITY_STABLE`: both
panels, the cached Exchange search behind the second one, and the degradation
paths for an unregistered or unreachable Exchange have been exercised against
a real two-site deployment.

One change lands with it. **The "Recent OER available" panel shows cover
images**: each row now leads with the resource's cover-image thumbnail, with a
neutral panel of the same size where a resource has no cover, so rows stay
aligned. The thumbnail sits inside the same link as the title but is hidden
from assistive technology, widening the click target without announcing the
same destination twice.

The thumbnail URL arrives over the network from the Exchange, so it passes
`clean_param(..., PARAM_URL)` before rendering — the same treatment the
creator-profile URL already gets.
