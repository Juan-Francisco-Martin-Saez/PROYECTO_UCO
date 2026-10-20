import {createApiClient} from './api-client.js';
export const createCartService=getToken=>{const api=createApiClient('../api/cart.php',getToken,'Error al consultar el carrito');return {list:()=>api('list'),set:(product_id,quantity)=>api('set',{method:'POST',body:new URLSearchParams({product_id,quantity})})}};
