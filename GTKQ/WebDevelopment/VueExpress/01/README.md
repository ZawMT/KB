### A simple math application 
This app is created to demonstrate the use of Vue.js (Frontend framework) and Express.js (Backend framework).

In the backend folder, run the following to start the backend service:
```
npm install
npm start
```

In the frontend folder, run these:
```
npm install
npm run dev
```

***Note***
package-lock.json will be generated after running `npm install`. 
This file should be added to git when the source codes are for the applications (web apps, servers, anything which is to deploy and run). It locks exact dependency versions, ensuring everyone on the team gets the exact same packages as those on the running dev/prod server (if any).
If the source codes are for libraries/packages (npm packages to publish for others to use), it should be ignored (i.e. added to .gitignore).