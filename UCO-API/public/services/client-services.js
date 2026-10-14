/* Servicios de clientes, reutilizables sin acoplamiento al DOM. */
import {createApiClient} from './api-client.js';
export function createClientServices(getToken){
 const request=createApiClient('../api/client.php',getToken,'No se pudo completar la operación');
 const auth={session:()=>request('session'),login:body=>request('login',{method:'POST',body}),register:body=>request('register',{method:'POST',body}),logout:()=>request('logout',{method:'POST'}),changePassword:body=>request('change_password',{method:'POST',body})};
 const catalog={list:params=>request('catalog',{params})};
 return {request,auth,catalog};
}
