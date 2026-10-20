import {createApiClient} from './api-client.js';
// Servicio de solo lectura. No confirma pedidos ni inicia cobros.
export const createCheckoutReviewService=getToken=>{
 const api=createApiClient('../api/checkout-review.php',getToken,'No se pudo revisar el pedido');
 return {review:()=>api('review')};
};
