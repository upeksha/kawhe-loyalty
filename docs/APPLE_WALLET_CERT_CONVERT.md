# Convert Certificate with Password

## The Issue

The `openssl pkcs12` command is prompting for passwords interactively. You need to provide them via command line flags.

## Solution: Use Password Flags

```bash
cd /var/www/kawhe/storage/app/private/passgenerator/certs

# Convert with password provided via command line
# Replace YOUR_PASSWORD with the actual certificate password from .env
openssl pkcs12 -in certificate.p12 -out certificate_new.p12 -legacy -passin pass:YOUR_PASSWORD -passout pass:YOUR_PASSWORD

# If successful, replace the old certificate
mv certificate_new.p12 certificate.p12
```

## Alternative: Non-Interactive Method

If you want to avoid putting password in command (for security):

```bash
cd /var/www/kawhe/storage/app/private/passgenerator/certs

# Create a temporary password file (one line with password)
echo "YOUR_PASSWORD" > /tmp/cert_pass.txt
chmod 600 /tmp/cert_pass.txt

# Convert using password file
openssl pkcs12 -in certificate.p12 -out certificate_new.p12 -legacy -passin file:/tmp/cert_pass.txt -passout file:/tmp/cert_pass.txt

# Clean up
rm /tmp/cert_pass.txt

# Replace old certificate
mv certificate_new.p12 certificate.p12
```

## Verify the Conversion Worked

```bash
# Test reading the certificate (should work without -legacy flag now)
openssl pkcs12 -info -in certificate.p12 -passin pass:YOUR_PASSWORD -noout
```

If this works, the certificate is now compatible.

## Important Notes

1. **Backup first**: You already did this with `certificate.p12.backup` ✓
2. **Use the same password**: The new certificate should use the same password as the old one (or update `.env` if you change it)
3. **Test after conversion**: Try generating a pass again

## If Conversion Still Fails

The certificate might need to be re-exported from Keychain on Mac with legacy encryption. The conversion method only works if the certificate format allows it.

**Best approach**: Re-export from Keychain Access on Mac with "Legacy" encryption selected.
