"use client";
import Link from "next/link";
import { FormEvent, useState } from "react";
import { api } from "../lib/api";
import FeedbackPopup from "../components/FeedbackPopup";

export default function ForgotPasswordPage() {
 const [message,setMessage]=useState(""); const [error,setError]=useState(""); const [busy,setBusy]=useState(false);
 async function submit(event:FormEvent<HTMLFormElement>){event.preventDefault();setMessage("");setError("");setBusy(true);const form=new FormData(event.currentTarget);try{const result=await api<{message:string}>("/forgot-password",{method:"POST",body:JSON.stringify({email:form.get("email")})});setMessage(result.message);}catch(e){setError(e instanceof Error?e.message:"Could not submit the request.");}finally{setBusy(false);}}
 return <main className="auth-shell">{message&&<FeedbackPopup message={message} onDismiss={()=>setMessage("")}/>}<Link className="brand" href="/">◉ CityPulse</Link><section className="auth-card"><span className="eyebrow">ACCOUNT RECOVERY</span><h1>Find your way<br/>back in.</h1><p className="muted">Enter your account email. If it can receive a reset link, we will send instructions.</p><form onSubmit={submit}><label>Email address<input type="email" name="email" placeholder="you@example.com" required autoComplete="email"/></label>{error&&<p className="form-error" role="alert">{error}</p>}<button className="button button-primary full" type="submit" disabled={busy}>{busy?"Sending…":"Send reset link →"}</button></form><p className="auth-bottom"><Link href="/login">← Back to sign in</Link></p></section></main>;
}
