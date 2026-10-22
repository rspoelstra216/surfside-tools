export const validId = (id) => typeof id === "string" && /^[A-Za-z0-9_-]{11}$/.test(id);
export const sourceKey = (id) => `sermons/${id}.mp4`;
export const audioKey = (id) => `podcast-audio/${id}.mp3`;
export const statusKey = (id) => `podcast-jobs/${id}.json`;

export function validStorageAccess(key, method) {
  if (method === "GET") {
    return /^sermons\/[A-Za-z0-9_-]{11}\.mp4$/.test(key) ||
      key === "podcast-assets/intro.mp3" || key === "podcast-assets/outro.mp3";
  }
  return method === "PUT" && /^podcast-audio\/[A-Za-z0-9_-]{11}\.mp3$/.test(key);
}

export async function verifiedRequest(request, secret, now = Date.now()) {
  if (typeof secret !== "string" || secret.length < 32) return false;
  const timestamp = request.headers.get("x-surfside-time") || "";
  const signature = request.headers.get("x-surfside-signature") || "";
  if (!/^\d{10}$/.test(timestamp) || !/^[a-f0-9]{64}$/.test(signature)) return false;
  if (Math.abs(now / 1000 - Number(timestamp)) > 300) return false;
  const bytes = new TextEncoder();
  const key = await crypto.subtle.importKey("raw", bytes.encode(secret),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  const body = await request.clone().text();
  const digest = await crypto.subtle.sign("HMAC", key, bytes.encode(timestamp + "." + body));
  const received = Uint8Array.from(signature.match(/.{2}/g).map((h) => parseInt(h, 16)));
  const expected = new Uint8Array(digest);
  let difference = 0;
  for (let i = 0; i < expected.length; i++) difference |= expected[i] ^ received[i];
  return difference === 0;
}
