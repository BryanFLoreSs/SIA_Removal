"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";
import { api, clearSession, CityPulseUser } from "../lib/api";

type Weather = { location: string; temperature_c: number; feels_like_c: number; condition: string; humidity: number; wind_mps: number };
type DashboardUser = CityPulseUser & { mfa_enabled: boolean };
type Place = { latitude: number; longitude: number; label: string };

const defaultPlace: Place = { latitude: 14.5995, longitude: 120.9842, label: "Manila, Philippines" };
const mapsKey = process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY;

function getWeather(place: Place, token: string) {
  const query = new URLSearchParams({ city: place.label, latitude: String(place.latitude), longitude: String(place.longitude) });
  return api<Weather>(`/weather?${query.toString()}`, {}, token);
}

export default function DashboardPage() {
  const router = useRouter();
  const [user, setUser] = useState<DashboardUser | null>(null);
  const [weather, setWeather] = useState<Weather | null>(null);
  const [weatherError, setWeatherError] = useState("");
  const [place, setPlace] = useState(defaultPlace);
  const [locationBusy, setLocationBusy] = useState(false);
  const [locationError, setLocationError] = useState("");
  const [busy, setBusy] = useState(true);
  const [loggingOut, setLoggingOut] = useState(false);
  const [mfaSecret, setMfaSecret] = useState("");
  const [mfaMessage, setMfaMessage] = useState("");
  const [mfaError, setMfaError] = useState("");
  const [mfaBusy, setMfaBusy] = useState(false);

  useEffect(() => {
    let alive = true;
    const token = localStorage.getItem("citypulse_token");
    if (!token) { router.replace("/login"); return; }
    api<{ user: DashboardUser }>("/me", {}, token)
      .then(({ user: currentUser }) => {
        if (!alive) return;
        setUser(currentUser);
        return getWeather(defaultPlace, token).then(setWeather).catch((error) => setWeatherError(error instanceof Error ? error.message : "Weather could not be loaded."));
      })
      .catch(() => { clearSession(); router.replace("/login"); })
      .finally(() => { if (alive) setBusy(false); });
    return () => { alive = false; };
  }, [router]);

  async function logout() {
    setLoggingOut(true);
    const token = localStorage.getItem("citypulse_token");
    try { if (token) await api("/logout", { method: "POST" }, token); }
    catch { /* Clear the local session even if the API is unavailable. */ }
    finally { clearSession(); router.replace("/login"); }
  }

  function useMyLocation() {
    setLocationError("");
    if (!navigator.geolocation) { setLocationError("This browser does not support location detection."); return; }
    setLocationBusy(true);
    navigator.geolocation.getCurrentPosition(async ({ coords }) => {
      const currentPlace: Place = { latitude: coords.latitude, longitude: coords.longitude, label: "Your current location" };
      setPlace(currentPlace);
      setWeather(null);
      setWeatherError("");
      const token = localStorage.getItem("citypulse_token");
      if (!token) { router.replace("/login"); return; }
      try { setWeather(await getWeather(currentPlace, token)); }
      catch (error) { setWeatherError(error instanceof Error ? error.message : "Weather could not be loaded."); }
      finally { setLocationBusy(false); }
    }, (error) => {
      setLocationBusy(false);
      setLocationError(error.code === error.PERMISSION_DENIED ? "Location permission was denied. You can enable it in your browser settings." : "Could not determine your location. Try again or allow location access.");
    }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
  }

  async function startMfa() {
    setMfaError(""); setMfaMessage("");
    const token = localStorage.getItem("citypulse_token");
    if (!token) return;
    setMfaBusy(true);
    try { const result = await api<{ secret: string }>("/mfa/setup", { method: "POST" }, token); setMfaSecret(result.secret); }
    catch (error) { setMfaError(error instanceof Error ? error.message : "Could not start MFA setup."); }
    finally { setMfaBusy(false); }
  }

  async function confirmMfa(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setMfaError(""); setMfaMessage("");
    const token = localStorage.getItem("citypulse_token");
    if (!token) return;
    setMfaBusy(true);
    try {
      const result = await api<{ message: string }>("/mfa/enable", { method: "POST", body: JSON.stringify({ code: new FormData(event.currentTarget).get("mfa_code") }) }, token);
      setMfaMessage(result.message); setMfaSecret(""); setUser((current) => current ? { ...current, mfa_enabled: true } : current);
    } catch (error) { setMfaError(error instanceof Error ? error.message : "Could not verify the authenticator code."); }
    finally { setMfaBusy(false); }
  }

  if (busy || !user) return <main className="dashboard"><header className="topbar"><Link className="brand" href="/">◉ CityPulse</Link></header><p className="loading-state">Checking your secure session…</p></main>;

  const mapUrl = mapsKey
    ? `https://www.google.com/maps/embed/v1/place?key=${encodeURIComponent(mapsKey)}&q=${encodeURIComponent(`${place.latitude},${place.longitude}`)}&zoom=14`
    : "";
  const externalMapUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${place.latitude},${place.longitude}`)}`;

  return <main className="dashboard">
    <header className="topbar"><Link className="brand" href="/">◉ CityPulse</Link><div className="navlinks"><span className="user-pill"><span className="avatar">{user.name.split(/\s+/).map((part) => part[0]).slice(0, 2).join("").toUpperCase()}</span><span>{user.name}</span></span><button className="button button-quiet" onClick={logout} disabled={loggingOut}>{loggingOut ? "Signing out…" : "Log out"}</button></div></header>
    <section className="dash-heading"><div><span className="eyebrow">YOUR DASHBOARD · CITYPULSE</span><h1>Hello, {user.name.split(" ")[0]}.</h1><p className="muted">A little clarity for wherever today takes you.</p></div><div className="location-select">⌖ <span>{place.label}</span></div></section>
    <section className="dash-grid">
      <article className="weather-card"><div className="card-top"><span className="eyebrow">CURRENT WEATHER</span><span className={`live-indicator ${weather ? "is-live" : ""}`}>● {weather ? "LIVE" : "WEATHER"}</span></div>
        {weather ? <><div className="weather-main"><div><div className="temperature">{Math.round(weather.temperature_c)}<span>°</span></div><div className="condition">{weather.condition}</div></div><div className="sun-icon">☼</div></div><div className="weather-place">{weather.location}</div><div className="weather-stats"><div><span>HUMIDITY</span><strong>{weather.humidity}%</strong></div><div><span>WIND</span><strong>{(weather.wind_mps * 3.6).toFixed(0)} km/h</strong></div><div><span>FEELS LIKE</span><strong>{Math.round(weather.feels_like_c)}°C</strong></div></div></> : <div className="weather-empty"><div className="temperature">—<span>°</span></div><p>{weatherError || "Loading current conditions…"}</p><button className="text-button" onClick={async () => { const token = localStorage.getItem("citypulse_token"); if (!token) return; setWeatherError(""); try { setWeather(await getWeather(place, token)); } catch (error) { setWeatherError(error instanceof Error ? error.message : "Weather could not be loaded."); } }}>Try again ↗</button></div>}
        <p className="notice">Weather data is requested from Laravel and the provider key stays on the server.</p>
      </article>
      <article className="map-card"><div className="map-title"><div><span className="eyebrow">GOOGLE MAPS</span><h2>Explore around you</h2></div><span className="map-marker">⌖</span></div>
        {mapUrl ? <iframe className="map-frame" title={`Google Map centered on ${place.label}`} src={mapUrl} loading="lazy" referrerPolicy="strict-origin-when-cross-origin" allowFullScreen /> : <div className="map-unconfigured"><strong>Google Maps needs a browser key.</strong><span>Set NEXT_PUBLIC_GOOGLE_MAPS_API_KEY in frontend/.env.local and enable Maps Embed API.</span><a href={externalMapUrl} target="_blank" rel="noreferrer">Open this place in Google Maps ↗</a></div>}
        <div className="map-controls"><button className="button button-primary" onClick={useMyLocation} disabled={locationBusy}>{locationBusy ? "Finding you…" : "⌖ Use my current location"}</button>{locationError && <p className="form-error" role="alert">{locationError}</p>}<p className="map-foot"><span>{place.label.toUpperCase()}</span><a href={externalMapUrl} target="_blank" rel="noreferrer">OPEN IN GOOGLE MAPS ↗</a></p></div>
      </article>
    </section>
    <section className="mfa-card"><div><span className="eyebrow">ACCOUNT SECURITY</span><h2>Authenticator MFA</h2><p>{user.mfa_enabled ? "Enabled. Sign-ins require a current code from your authenticator app." : "Add a time-based one-time code to protect your account."}</p></div>{!user.mfa_enabled && !mfaSecret && <button className="button button-quiet" disabled={mfaBusy} onClick={startMfa}>{mfaBusy ? "Preparing…" : "Set up authenticator"}</button>}{mfaSecret && <div className="mfa-setup"><p>Add this setup key to your authenticator app:</p><code>{mfaSecret}</code><p className="muted">Choose time-based (TOTP), six digits, 30-second interval.</p><form onSubmit={confirmMfa}><label>Enter the current code<input name="mfa_code" inputMode="numeric" pattern="[0-9]{6}" maxLength={6} required autoComplete="one-time-code" /></label><button className="button button-primary" disabled={mfaBusy}>{mfaBusy ? "Verifying…" : "Enable MFA →"}</button></form></div>}{mfaMessage && <p className="form-success" role="status">{mfaMessage}</p>}{mfaError && <p className="form-error" role="alert">{mfaError}</p>}</section>
    <section className="bottom-note"><span className="note-icon">✦</span><div><strong>Signed in as {user.email}</strong><p>Your session is validated by the Laravel API. Signing out revokes this access token.</p></div><span className="note-arrow">↗</span></section>
    <footer><span>CityPulse · Your city, in clear view.</span><Link href="/">Back to home ↗</Link></footer>
  </main>;
}
