# Register.jsx

## Overview
- Source file: web frontend\src\pages\Register.jsx
- Category: Page Component
- Purpose: Implements the Register page UI and page-level interactions.

## What Is Done In This File
- Implements frontend logic for Register.
- Handles UI rendering, state behavior, API integration, or configuration as needed.
- Provides output consumed by the browser runtime and related modules.

## Key Lines (Quick Scan)
- import { useState, useRef } from "react"
- import { Link, useNavigate } from "react-router-dom"
- import "./Register.css"
- import { authApi } from "../api/auth"
- const [step, setStep] = useState(1) // 1 = email verification (OTP sent)

## Notes
- This is a concise generated note. Use source code for exact implementation details.
