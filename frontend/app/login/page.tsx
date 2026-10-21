"use client";
import Link from "next/link";
import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { api, AuthResponse, saveSession } from "../lib/api";

export default function LoginPage() {
  const router = useRouter();
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [challenge, setChallenge] = useState("");

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setBusy(true);
    const form = new FormData(event.currentTarget);
    try {
      const result = challenge
        ? await api<AuthResponse>("/mfa/challenge", { method: "POST", body: JSON.stringify({ challenge, code: form.get("code") }) })
        : await api<AuthResponse | { mfa_required: true; challenge: string }>("/login", { method: "POST", body: JSON.stringify({ email: form.get("email"), password: form.get("password") }) });
      if ("mfa_required" in result) {
        setChallenge(result.challenge);
        return;
      }
      saveSession(result);
      router.replace("/dashboard");
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not sign in.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className="auth-shell">
      <Link className="brand" href="/">◉ CityPulse</Link>
      <section className="auth-card">
        <span className="eyebrow">{challenge ? "TWO-STEP VERIFICATION" : "WELCOME BACK"}</span>
        <h1>{challenge ? <>Check your<br />authenticator.</> : <>Sign in to your<br />city dashboard.</>}</h1>
        <p className="muted">{challenge ? "Enter the current six-digit code from your authenticator app." : "Use your CityPulse account to continue."}</p>
        <form onSubmit={submit}>
          {challenge ? (
            <label>6-digit authenticator code<input type="text" name="code" inputMode="numeric" pattern="[0-9]{6}" maxLength={6} autoComplete="one-time-code" required autoFocus /></label>
          ) : (
            <>
              <label>Email address<input type="email" name="email" placeholder="you@example.com" required autoComplete="email" /></label>
              <label>Password<input type="password" name="password" placeholder="Enter your password" required autoComplete="current-password" minLength={8} /></label>
              <div className="form-row"><span>Protected account access</span><Link href="/forgot-password">Forgot password?</Link></div>
            </>
          )}
          {error && <p className="form-error" role="alert">{error}</p>}
          <button className="button button-primary full" type="submit" disabled={busy}>{busy ? "Please wait…" : challenge ? "Verify and sign in →" : "Continue →"}</button>
        </form>
        {challenge && <button className="text-button back-auth" onClick={() => { setChallenge(""); setError(""); }}>← Back to email and password</button>}
        <p className="auth-bottom">New here? <Link href="/register">Create an account</Link></p>
      </section>
    </main>
  );
}
