// Formats a Retry-After style duration for humans: 45 → "45s",
// 300 → "5m", 31193 → "8h 40m". Rounds up so we never promise a
// retry window shorter than the server's.
export function formatDuration(totalSeconds: number): string {
  const seconds = Math.max(0, Math.ceil(totalSeconds))

  if (seconds < 60) {
    return `${seconds}s`
  }

  const totalMinutes = Math.ceil(seconds / 60)

  if (totalMinutes < 60) {
    return `${totalMinutes}m`
  }

  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60

  return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`
}
