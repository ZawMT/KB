# Next.js — Creating Your First App

## Create the App

Navigate to the folder where this file resides. 
Then create a folder (e.g. testprj) to try out creating Next.js app.
And then go into (command 'cd') that folder and run one of the following (they all do the same thing):

```bash
# Using npx — no prior install needed, most common in docs
npx create-next-app@latest .

# Using npm
npm create next-app@latest .

# Using pnpm
pnpm create next-app .

# Using yarn
yarn create next-app .
```

The `.` at the end means "create the project in the current folder" instead of creating a new subfolder.

## Setup Prompts

The command will ask a few questions:

```
What is your project named? › my-app
Would you like to use TypeScript? › No / Yes
Would you like to use ESLint? › No / Yes
Would you like to use Tailwind CSS? › No / Yes
Would you like your code inside a `src/` directory? › No / Yes
Would you like to use App Router? (recommended) › No / Yes
Would you like to use Turbopack for next dev? › No / Yes
Would you like to customize the import alias? › No / Yes
```

For a basic start, you can accept the defaults.

## Run the App

```bash
npm run dev
# or
pnpm dev
# or
yarn dev
```

Then open your browser at `http://localhost:3000`.

## What Gets Created

```
testprj/
├── app/                  # Pages and API routes
│   ├── page.tsx          # The home page (http://localhost:3000/)
│   ├── layout.tsx        # Shared layout wrapping all pages
│   └── globals.css       # Global styles
├── public/               # Static files (images, icons, etc.)
├── next.config.ts        # Next.js configuration
├── package.json          # Project dependencies and scripts
├── tsconfig.json         # TypeScript configuration
└── node_modules/         # Installed dependencies (auto-generated)
```

## Key Files to Know

| File | Purpose |
|------|---------|
| `app/page.tsx` | The home page — edit this to change what you see at `/` |
| `app/layout.tsx` | Wraps every page — good for nav bars, fonts, global styles |
| `next.config.ts` | Next.js settings (rarely needed to touch at first) |
| `package.json` | Lists dependencies; defines `dev`, `build`, `start` scripts |


## Key Information

Next.js is built on top of Node.js and React. It is a framework, so the app that you created will have a full structure — so it is not as thin as a trimmed project which has only the files that are really required.