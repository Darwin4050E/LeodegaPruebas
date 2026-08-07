import { initializeApp } from 'firebase/app';
import { getStorage } from 'firebase/storage';


const firebaseConfig = {
    apiKey: "AIzaSyBbcsdsiFnMts58APLLS5Zgsb1MfMteoII",
    authDomain: "leodega-e9835.firebaseapp.com",
    projectId: "leodega-e9835",
}

const app = initializeApp(firebaseConfig);
export const storage = getStorage(app);