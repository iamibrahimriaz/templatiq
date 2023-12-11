import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Link } from 'react-router-dom';
import { AuthStyle } from "@root/style";

import { select } from '@wordpress/data';
import store from '@store/index';

export default function SignUpContent() {
	const [isRegistered, setIsRegistered] = useState(false);
	let [loading, setLoading] = useState(false);
	let [errorMessage, setErrorMessage] = useState('');

	const [formData, setFormData] = useState({
		authorFullName: "Ibrahim Riaz",
		authorEmail: "riaz@sovware.com",
	});
	
	const { isLoggedIn } = select( store ).getUserInfo();

	const handleChange = (e) => {
		setFormData({
		  	...formData,
		  	[e.target.name]: e.target.value,
		});
	};

	const handleData = (e) => {
		e.preventDefault(); 
		handleSignup({ name: authorFullName.value, user_email: authorEmail.value });
	};

	const handleSignup = async (credentials) => {
		apiFetch( { 
			path: 'templatiq/account/create',
			method: 'POST',
			data: credentials,
		}).then( ( res ) => {
			const signUpData = JSON.parse(res.body);
			console.log( 'Register User data: ', signUpData );
			if(signUpData.token) {
				setIsRegistered(true);
			} else {
				const errorMessage = signUpData.message?.user_email;
				setErrorMessage(errorMessage);
			}
		} );

		setLoading(false);
	};

	useEffect( () => {
		isLoggedIn && navigate('/');
	}, [] );
	

	return (
		<AuthStyle className="templatiq__auth">
			<h3 className="templatiq__auth__title">Sign up your account</h3>
			{
				!isRegistered ? 
				<form className='templatiq__auth__wrapper' onSubmit={handleData}>
					<div className="templatiq__auth__info">
						<div className="templatiq__auth__info__single">
							<label htmlFor="authorFullName">Full Name</label>
							<input
								type="text"
								id="authorFullName"
								name="authorFullName"
								value={formData.authorFullName}
								onChange={handleChange}
							/>
						</div>
						<div className="templatiq__auth__info__single">
							<label htmlFor="authorEmail">Email Address</label>
							<input
								type="email"
								id="authorEmail"
								name="authorEmail"
								value={formData.authorEmail}
								onChange={handleChange}
							/>
						</div>
					</div>
					<div className="templatiq__auth__actions">
						<button 
							type="submit" 
							onClick={() => {setLoading(true)}}
							className={`templatiq__auth__btn templatiq-btn templatiq-btn-primary ${loading ? 'templatiq__loading templatiq__loading--btn' : ''}`}
						>
							Sign Up
						</button>
						{
							errorMessage && <p className="text-danger">{errorMessage}</p>
						}
						<span className="templatiq__auth__desc">
							Already have an account?
							<Link to="/signin" className="templatiq__auth__link">Sign in</Link>
						</span>
						
					</div>
				</form> : 
				<div className="templatiq__auth__wrapper notification-wrapper">
					<p>Successfully Registered. Check Your Email to Complete Registration. </p>
					<div class="templatiq__auth__btn_wrapper">
						<Link to="/" className="templatiq__auth__btn templatiq-btn templatiq-btn-primary">Go to Home</Link>
						<Link to="/signin" className="templatiq__auth__btn templatiq-btn">Sign in</Link>
					</div>
				</div>
			}
			
		</AuthStyle>
	);
}