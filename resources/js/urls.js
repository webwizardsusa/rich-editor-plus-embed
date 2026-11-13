// Characters browsers ignore in a URL's scheme, removed before reading it, as PHP's `ContentSpec::isAllowedUrl()` does.
const IGNORED_URL_CHARACTERS = /[\u0000-\u0020\u007F-\u00A0\u1680\u180E\u2000-\u2029\u205F\u3000]/gu

// The same test as PHP's `EmbedPlugin::isAllowedUrl()`: absolute http(s) only.
export const isAllowedUrl = (value) =>
    typeof value === 'string' && /^https?:\/\//i.test(value.replace(IGNORED_URL_CHARACTERS, ''))

/**
 * Whether the URL's host is one of the providers' hosts, where `*.example.com` also matches `example.com`, as
 * `Provider::hostMatches()` does. It only decides whether a pasted URL is worth asking the server about.
 */
export function matchesHost(url, hosts) {
    let host

    try {
        host = new URL(url).hostname.toLowerCase().replace(/\.$/, '')
    } catch {
        return false
    }

    return hosts.some((pattern) => {
        if (!pattern.startsWith('*.')) {
            return host === pattern
        }

        const domain = pattern.slice(2)

        return host === domain || host.endsWith(`.${domain}`)
    })
}
