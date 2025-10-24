/**
 * Universal fetch with retry logic for session cookie sync issues
 * Handles 401 Unauthorized and network errors with exponential backoff
 *
 * Usage:
 *   const response = await fetchWithRetry('api/data.php');
 *   const json = await response.json();
 *
 * @param {string} url - URL to fetch
 * @param {object} options - Fetch options (will be merged with { credentials: 'same-origin' })
 * @param {number} maxRetries - Maximum number of retry attempts (default: 3)
 * @param {number} initialDelay - Initial delay in ms (default: 1000, exponential backoff applied)
 * @returns {Promise<Response>} - Fetch Response object
 */
async function fetchWithRetry(url, options = {}, maxRetries = 3, initialDelay = 1000) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                ...options
            });

            // Success - return response
            if (response.ok) {
                if (attempt > 1) {
                    console.log(`[fetchWithRetry] ✅ Success on attempt ${attempt}/${maxRetries} for ${url}`);
                }
                return response;
            }

            // 401 Unauthorized - retry with exponential backoff
            if (response.status === 401 && attempt < maxRetries) {
                const delay = initialDelay * attempt;
                console.warn(`[fetchWithRetry] ⚠️ Attempt ${attempt}/${maxRetries}: 401 Unauthorized for ${url}, retrying in ${delay}ms...`);
                await new Promise(resolve => setTimeout(resolve, delay));
                continue;
            }

            // Other HTTP errors - throw immediately
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);

        } catch (error) {
            // Network errors or fetch failures
            if (attempt === maxRetries) {
                console.error(`[fetchWithRetry] ❌ Failed after ${maxRetries} attempts for ${url}:`, error.message);
                throw error;
            }

            const delay = initialDelay * attempt;
            console.warn(`[fetchWithRetry] ⚠️ Attempt ${attempt}/${maxRetries}: ${error.message} for ${url}, retrying in ${delay}ms...`);
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }

    throw new Error(`fetchWithRetry: Max retries (${maxRetries}) exceeded for ${url}`);
}
