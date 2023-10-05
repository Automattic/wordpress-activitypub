# Federation in WordPress

The WordPress plugin largely follows ActivityPub's server-to-server specification, but makes use of some non-standard extensions, some of which are required to interact with the plugin. Most of these extensions are for the purpose of compatibility with other, sometimes very restrictive networks, such as Mastodon.

## Supported federation protocols and standards

- [ActivityPub](https://www.w3.org/TR/activitypub/) (Server-to-Server)
- [WebFinger](https://webfinger.net/)
- [HTTP Signatures](https://datatracker.ietf.org/doc/html/draft-cavage-http-signatures)
- [NodeInfo](https://nodeinfo.diaspora.software/)

## Supported FEPs

- [FEP-f1d5: NodeInfo in Fediverse Software](https://codeberg.org/fediverse/fep/src/branch/main/fep/f1d5/fep-f1d5.md)
- [FEP-67ff: FEDERATION.md](https://codeberg.org/fediverse/fep/src/branch/main/fep/67ff/fep-67ff.md)
- [FEP-5feb: Search indexing consent for actors](https://codeberg.org/fediverse/fep/src/branch/main/fep/5feb/fep-5feb.md)

Partially supported FEPs

- [FEP-1b12: Group federation](https://codeberg.org/fediverse/fep/src/branch/main/fep/1b12/fep-1b12.md)

## ActivityPub

### HTTP Signatures

In order to authenticate activities, Mastodon relies on HTTP Signatures, signing every `POST` and `GET` request to other ActivityPub implementations on behalf of the user authoring an activity (for `POST` requests) or an actor representing the Mastodon server itself (for most `GET` requests).

Mastodon requires all `POST` requests to be signed, and MAY require `GET` requests to be signed, depending on the configuration of the Mastodon server.

More information on HTTP Signatures, as well as examples, can be found here: https://docs.joinmastodon.org/spec/security/#http

## Additional documentation

- Plugin Description: https://github.com/Automattic/wordpress-activitypub?tab=readme-ov-file#description
- Frequently Asked Questions: https://github.com/Automattic/wordpress-activitypub?tab=readme-ov-file#frequently-asked-questions
- Installation Instructions: https://github.com/Automattic/wordpress-activitypub?tab=readme-ov-file#installation
- Upgrade Notice: https://github.com/Automattic/wordpress-activitypub?tab=readme-ov-file#upgrade-notice
- Changelog: https://github.com/Automattic/wordpress-activitypub?tab=readme-ov-file#changelog
