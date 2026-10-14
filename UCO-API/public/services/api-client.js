/* Transporte reutilizable. La API PHP mantiene autenticación y autorización. */
export function createApiClient(endpoint,getToken=()=>'',defaultError='Error de servidor'){
 return async function(action,{method='GET',body,params}={}){
  const url=new URL(endpoint,location.href);url.searchParams.set('action',action);
  for(const [key,value] of Object.entries(params||{}))url.searchParams.set(key,value);
  const csrf=getToken();const response=await fetch(url,{method,body,headers:csrf?{'X-CSRF-Token':csrf}:{},credentials:'same-origin',cache:'no-store'});
  let data;try{data=await response.json()}catch{throw Error(defaultError)}
  if(!response.ok){const error=new Error(data.error||defaultError);error.status=response.status;error.details=data;throw error}return data;
 }
}
