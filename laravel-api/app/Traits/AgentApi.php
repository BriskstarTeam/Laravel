<?php
namespace App\Traits;

/**
 * Trait AgentApi
 * @package App\Traits
 */
trait AgentApi {

    /**
     * @param string $agentIds
     * @return bool|string
     */
    public function getAgentByIds($agentIds = ''){
        if( $agentIds != '') {
            $user = array('ids' => $agentIds);
            $ch    = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, 'http://103.254.245.42:10008/Login/GetListingSelectedAgentList');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $user);
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        }
    }

    /**
     * @param string $user
     * @return bool|string
     */
    public function getAgentByUsername( $user = '' ) {
        if(empty($user)){
            $user = array('userName' => '');
        }else{
            $user = array('userName' => $user);
        }

        $ch    = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, 'http://103.254.245.42:10008/Login/GetListingSelectedAgentListByUserName');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $user);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
