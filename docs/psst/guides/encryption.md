# How the encryption works

Everything sensitive happens in the browser. The server validates the shape of what it
receives and stores it; it has no key and cannot decrypt.

```
urlKey  = 32 random bytes                      (in the link fragment, never sent)
ppBits  = passphrase ? PBKDF2-SHA-256(passphrase, salt, 600000) : empty
aesKey  = HKDF-SHA-256(ikm = urlKey, salt = ppBits, info = "psst/2/content")
chkKey  = HKDF-SHA-256(ikm = urlKey, salt = ppBits, info = "psst/2/check")
check   = HMAC-SHA-256(chkKey, "psst")[0..16]
ct      = AES-256-GCM(aesKey, iv, plaintext, aad = "psst/2;pp=0|1")
```

The stored envelope is `iv`, `ct`, the PBKDF2 parameters and salt when a pass phrase was
used, and `check`. The `check` value lets the recipient tell a wrong pass phrase apart from
a corrupted envelope without asking the server, which matters because the reveal call is
one-shot: the browser fetches the envelope once, the server deletes it in the same request,
and pass phrase retries happen against the copy the browser holds.

Reveal is a `POST` with a JSON content type. Link previewers and mail scanners only issue
`GET`, and an HTML form cannot send JSON, so nothing but the viewer's button consumes a
secret. The claim on the server is a conditional `UPDATE`, so concurrent reveals hand the
envelope to exactly one caller.
