"use client";
import Link from "next/link";
import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { api, AuthResponse, saveSession } from "../lib/api";

export default function RegisterPage() {
  const router = useRouter(); const [error, setError] = useState(""); const [busy, setBusy] = useState(false);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(""); setBusy(true); const form = new FormData(event.currentTarget);
    if (form.get("password") !== form.get("password_confirmation")) { setError("The passwords do not match."); setBusy(false); return; }
    try { const result = await api<AuthResponse>("/register", { method: "POST", body: JSON.stringify({ name: form.get("name"), email: form.get("email"), password: form.get("password"), password_confirmation: form.get("password_confirmation") }) }); saveSession(result); router.replace("/dashboard"); }
    catch (e) { setError(e instanceof Error ? e.message : "Could not create your account."); }
    finally { setBusy(false); }
  }
  return <main className="auth-shell"><Link className="brand" href="/">◉ CityPulse</Link><section className="auth-card"><span className="eyebrow">START EXPLORING</span><h1>Create your<br/>CityPulse account.</h1><p className="muted">Set up a personal space for your city dashboard.</p><form onSubmit={submit}><label>Full name<input name="name" placeholder="Example Bryan" required autoComplete="name" maxLength={120}/></label><label>Email address<input type="email" name="email" placeholder="you@example.com" required autoComplete="email"/></label><label>Password<input type="password" name="password" placeholder="At least 12 characters" required minLength={12} autoComplete="new-password"/></label><label>Confirm password<input type="password" name="password_confirmation" placeholder="Enter password again" required minLength={12} autoComplete="new-password"/></label>{error && <p className="form-error" role="alert">{error}</p>}<button className="button button-primary full" type="submit" disabled={busy}>{busy ? "Creating account…" : "Create account →"}</button></form><p className="auth-bottom">Already registered? <Link href="/login">Sign in</Link></p></section></main>;
}
