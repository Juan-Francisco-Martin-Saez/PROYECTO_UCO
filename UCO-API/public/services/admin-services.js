/* Servicios de dominio. Ninguno accede al DOM ni conoce los Web Components. */
import {createApiClient} from './api-client.js';
export function createAdminServices(getToken){
 const request=createApiClient('../api/admin.php',getToken);
 const products={list:params=>request('list',{params}),save:body=>request('save',{method:'POST',body})};
 const users={list:()=>request('users'),save:body=>request('user_save',{method:'POST',body}),remove:body=>request('user_delete',{method:'POST',body})};
 const commercial={list:()=>request('commercial_list'),saveCustomer:body=>request('customer_save',{method:'POST',body}),saveGroup:body=>request('group_save',{method:'POST',body}),savePrice:body=>request('price_save',{method:'POST',body})};
 const auth={session:()=>request('session'),login:body=>request('login',{method:'POST',body}),logout:()=>request('logout',{method:'POST'}),changePassword:body=>request('change_password',{method:'POST',body})};
 return {request,products,users,commercial,auth};
}
